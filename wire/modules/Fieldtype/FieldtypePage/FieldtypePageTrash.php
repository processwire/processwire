<?php namespace ProcessWire;

/**
 * Handles removal and restoration of Page field references when pages are trashed
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */
class FieldtypePageTrash extends Wire {

	const metaVersion = 1;
	const metaKey = 'FieldtypePage.trashRefs';

	/**
	 * Add trash and restore hooks
	 *
	 */
	public function init() {
		$pages = $this->wire()->pages;
		if($pages) {
			$pages->addHookAfter('trash', $this, 'hookPagesTrash');
			$pages->addHookAfter('restore', $this, 'hookPagesRestore');
		} else {
			$this->addHookAfter('Pages::trash', $this, 'hookPagesTrash');
			$this->addHookAfter('Pages::restore', $this, 'hookPagesRestore');
		}
	}

	/**
	 * Remove configured references to a page after it has been moved to the trash
	 *
	 * @param HookEvent $event
	 *
	 */
	public function hookPagesTrash($event) {
		if(!$event->return) return;
		$page = $event->arguments[0];
		if(!$page instanceof Page || !$page->id) return;
		$fields = $this->getTrashPageRefFields();
		if(!count($fields)) return;
		$pageIds = $this->getTrashPageBranchIds($page);
		foreach(array_chunk(array_values($pageIds), FieldtypePage::deleteChunkSize) as $ids) {
			$this->removeTrashPageRefs($ids, $fields);
		}
	}

	/**
	 * Restore configured references after a page has been restored from the trash
	 *
	 * @param HookEvent $event
	 *
	 */
	public function hookPagesRestore($event) {
		if(!$event->return) return;
		$page = $event->arguments[0];
		if(!$page instanceof Page || !$page->id) return;
		$this->restoreTrashPageRefs($this->getTrashPageBranchIds($page));
	}

	/**
	 * Get Page reference fields configured to remove references to trashed pages
	 *
	 * @return Field[]
	 *
	 */
	protected function getTrashPageRefFields() {
		$fields = array();
		foreach($this->wire()->fields->findByType('FieldtypePage') as $field) {
			if((int) $field->get('trashPageRefs') === FieldtypePage::trashPageRefsRemove) $fields[] = $field;
		}
		return $fields;
	}

	/**
	 * Get IDs for the given page and all pages below it
	 *
	 * @param Page $page
	 * @return array Page IDs indexed by page ID
	 *
	 */
	protected function getTrashPageBranchIds(Page $page) {
		$ids = array($page->id => $page->id);
		foreach($this->wire()->pages->findIDs("has_parent=$page->id, include=all") as $id) {
			$id = (int) $id;
			if($id > 0) $ids[$id] = $id;
		}
		return $ids;
	}

	/**
	 * Remove configured references to trashed pages and save rows in page meta
	 *
	 * @param array $pageIds Trashed page IDs
	 * @param Field[] $fields Configured Page reference fields
	 *
	 */
	protected function removeTrashPageRefs(array $pageIds, array $fields) {
		$database = $this->wire()->database;
		$refs = array();
		$ids = implode(',', array_map('intval', $pageIds));
		if(!strlen($ids)) return;

		foreach($fields as $field) {
			$table = $database->escapeTable($field->table);
			$query = $database->prepare("SELECT * FROM `$table` WHERE data IN($ids) ORDER BY data, pages_id, sort");
			$query->execute();
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$targetId = (int) $row['data'];
				$refs[$targetId][] = array(
					'field_id' => (int) $field->id,
					'row' => $row,
				);
			}
			$query->closeCursor();
		}

		if(!count($refs)) return;
		if(!$database->tableExists('pages_meta')) {
			$target = $this->wire()->pages->get((int) key($refs));
			if($target->id) $target->meta()->install();
		}
		$useTransaction = $database->allowTransaction();
		if($useTransaction) $database->beginTransaction();

		try {
			foreach($refs as $targetId => $targetRefs) {
				$target = $this->wire()->pages->get((int) $targetId);
				if(!$target->id) continue;
				$meta = $target->meta(self::metaKey);
				if(!is_array($meta) || !isset($meta['version'], $meta['refs']) ||
					$meta['version'] !== self::metaVersion || !is_array($meta['refs'])) {
					$meta = array('version' => self::metaVersion, 'refs' => array());
				}
				$keys = array();
				foreach($meta['refs'] as $ref) {
					if(!isset($ref['field_id'], $ref['row']['pages_id'], $ref['row']['data'], $ref['row']['sort'])) continue;
					$key = implode(':', array($ref['field_id'], $ref['row']['pages_id'], $ref['row']['data'], $ref['row']['sort']));
					$keys[$key] = true;
				}
				foreach($targetRefs as $ref) {
					$key = implode(':', array($ref['field_id'], $ref['row']['pages_id'], $ref['row']['data'], $ref['row']['sort']));
					if(isset($keys[$key])) continue;
					$meta['refs'][] = $ref;
					$keys[$key] = true;
				}
				$target->meta(self::metaKey, $meta);
				$this->verifyTrashPageRefsMeta($target, $meta);
			}

			foreach($fields as $field) {
				$table = $database->escapeTable($field->table);
				$query = $database->prepare("DELETE FROM `$table` WHERE data IN($ids)");
				$query->execute();
			}
			if($useTransaction) $database->commit();
		} catch(\Exception $e) {
			if($useTransaction && $database->inTransaction()) $database->rollBack();
			throw $e;
		}
	}

	/**
	 * Verify that reference metadata was saved
	 *
	 * WireDataDB intentionally suppresses database errors, but these references must
	 * not be deleted from field tables unless their metadata was persisted.
	 *
	 * @param Page $page
	 * @param array $expected
	 * @throws WireException
	 *
	 */
	protected function verifyTrashPageRefsMeta(Page $page, array $expected) {
		$query = $this->wire()->database->prepare(
			'SELECT data FROM pages_meta WHERE source_id=:source_id AND name=:name'
		);
		$query->bindValue(':source_id', $page->id, \PDO::PARAM_INT);
		$query->bindValue(':name', self::metaKey);
		$query->execute();
		$data = $query->fetchColumn();
		$query->closeCursor();
		if($data !== false && json_decode($data, true) === $expected) return;
		throw new WireException("Unable to persist trash references for page $page->id");
	}

	/**
	 * Restore Page reference rows previously removed when pages were trashed
	 *
	 * Multiple-value fields retain current values and insert restored references at
	 * their previous positions. For a single-value field, a current value takes
	 * precedence over the previously removed reference.
	 *
	 * @param array $pageIds Restored page IDs
	 *
	 */
	protected function restoreTrashPageRefs(array $pageIds) {
		$database = $this->wire()->database;
		$groups = array();
		$targets = array();
		if(!$database->tableExists('pages_meta')) return;

		foreach(array_chunk(array_values($pageIds), FieldtypePage::deleteChunkSize) as $pageIdChunk) {
			$ids = implode(',', array_map('intval', $pageIdChunk));
			$query = $database->prepare(
				"SELECT source_id, data FROM pages_meta WHERE name=:name AND source_id IN($ids)"
			);
			$query->bindValue(':name', self::metaKey);
			$query->execute();
			while($metaRow = $query->fetch(\PDO::FETCH_ASSOC)) {
				$targetId = (int) $metaRow['source_id'];
				$meta = json_decode($metaRow['data'], true);
				if(!is_array($meta) || !isset($meta['version'], $meta['refs']) ||
					$meta['version'] !== self::metaVersion || !is_array($meta['refs'])) continue;
				$targets[$targetId] = $targetId;
				foreach($meta['refs'] as $ref) {
					if(!isset($ref['field_id'], $ref['row']) || !is_array($ref['row'])) continue;
					$row = $ref['row'];
					if(!isset($row['pages_id'], $row['data'], $row['sort'])) continue;
					$fieldId = (int) $ref['field_id'];
					$sourceId = (int) $row['pages_id'];
					if(!$fieldId || !$sourceId || (int) $row['data'] !== $targetId) continue;
					$groups[$fieldId][$sourceId][] = $row;
				}
			}
			$query->closeCursor();
		}

		if(!count($targets)) return;
		foreach($groups as $fieldId => $sourceGroups) {
			$field = $this->wire()->fields->get((int) $fieldId);
			if(!$field || !$field->id || !($field->type instanceof FieldtypePage)) continue;
			foreach($sourceGroups as $sourceId => $rows) {
				$this->restoreTrashPageRefGroup($field, (int) $sourceId, $rows);
			}
		}
		$this->removeTrashPageRefsMeta($targets);
	}

	/**
	 * Restore rows for one Page reference field on one source page
	 *
	 * @param Field $field
	 * @param int $sourceId
	 * @param array $rows
	 *
	 */
	protected function restoreTrashPageRefGroup(Field $field, $sourceId, array $rows) {
		$database = $this->wire()->database;
		$source = $this->wire()->pages->get((int) $sourceId);
		if(!$source->id || !$source->hasField($field)) return;
		$table = $database->escapeTable($field->table);
		$columns = array_flip($database->getColumns($table));
		if(!isset($columns['pages_id'], $columns['data'], $columns['sort'])) return;
		$useTransaction = $database->allowTransaction($table);
		if($useTransaction) $database->beginTransaction();

		try {
			$current = array();
			$currentCount = 0;
			$query = $database->prepare("SELECT data FROM `$table` WHERE pages_id=:pages_id");
			$query->bindValue(':pages_id', $sourceId, \PDO::PARAM_INT);
			$query->execute();
			while(($data = $query->fetchColumn()) !== false) {
				$current[(int) $data] = true;
				$currentCount++;
			}
			$query->closeCursor();
			usort($rows, function($a, $b) {
				return (int) $a['sort'] <=> (int) $b['sort'];
			});
			$shift = $database->prepare(
				"UPDATE `$table` SET sort=sort+1 WHERE pages_id=:pages_id AND sort>=:sort ORDER BY sort DESC"
			);

			foreach($rows as $row) {
				if((int) $field->get('derefAsPage') > 0 && $currentCount) break;
				$data = (int) $row['data'];
				if(isset($current[$data])) continue;
				$sort = min(max(0, (int) $row['sort']), $currentCount);
				$shift->bindValue(':pages_id', $sourceId, \PDO::PARAM_INT);
				$shift->bindValue(':sort', $sort, \PDO::PARAM_INT);
				$shift->execute();

				$row['pages_id'] = $sourceId;
				$row['sort'] = $sort;
				$insertCols = array();
				$insertBinds = array();
				foreach($row as $col => $value) {
					if(!isset($columns[$col])) continue;
					$col = $database->escapeCol($col);
					$insertCols[] = $col;
					$insertBinds[":$col"] = $value;
				}
				$sql = "INSERT INTO `$table` (`" . implode('`, `', $insertCols) . "`) VALUES(" . implode(', ', array_keys($insertBinds)) . ")";
				$insert = $database->prepare($sql);
				foreach($insertBinds as $key => $value) $insert->bindValue($key, $value);
				$insert->execute();
				$current[$data] = true;
				$currentCount++;
			}
			if($useTransaction) $database->commit();
		} catch(\Exception $e) {
			if($useTransaction && $database->inTransaction()) $database->rollBack();
			throw $e;
		}
	}

	/**
	 * Remove restored reference metadata in bounded chunks
	 *
	 * @param array $pageIds Page IDs indexed by page ID
	 * @throws WireException
	 *
	 */
	protected function removeTrashPageRefsMeta(array $pageIds) {
		$database = $this->wire()->database;
		foreach(array_chunk(array_values($pageIds), FieldtypePage::deleteChunkSize) as $pageIdChunk) {
			$ids = implode(',', array_map('intval', $pageIdChunk));
			$useTransaction = $database->allowTransaction('pages_meta');
			if($useTransaction) $database->beginTransaction();
			try {
				$query = $database->prepare(
					"DELETE FROM pages_meta WHERE name=:name AND source_id IN($ids)"
				);
				$query->bindValue(':name', self::metaKey);
				$query->execute();
				$query = $database->prepare(
					"SELECT COUNT(*) FROM pages_meta WHERE name=:name AND source_id IN($ids)"
				);
				$query->bindValue(':name', self::metaKey);
				$query->execute();
				if((int) $query->fetchColumn()) {
					throw new WireException('Unable to remove restored trash references');
				}
				if($useTransaction) $database->commit();
			} catch(\Exception $e) {
				if($useTransaction && $database->inTransaction()) $database->rollBack();
				throw $e;
			}
			foreach($pageIdChunk as $pageId) {
				$page = $this->wire()->pages->get((int) $pageId);
				if($page->id) $page->meta()->reset();
			}
		}
	}
}
