<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireClassLoader
 *
 */
class WireTest_WireClassLoader extends WireTest {

	protected $path = '';
	protected $namespace = '';
	protected $namespaces = array();

	public function init() {
		$this->path = $this->wire()->config->paths->cache . 'WireTests/WireClassLoader/';
		$this->namespace = 'WireTestsClassLoader' . getmypid();
		$this->cleanupFiles();
		if(!is_dir($this->path)) $this->wire()->files->mkdir($this->path, true);
	}

	public function execute() {
		$classLoader = $this->wire()->classLoader;

		$this->check('$classLoader is WireClassLoader', true, $classLoader instanceof WireClassLoader);
		$this->check('findClassFile() returns false for unknown class', false, $classLoader->findClassFile('ProcessWire\\WireTestsNoSuchClass'));

		$this->testNamespaces($classLoader);
		$this->testExtensions($classLoader);
		$this->testPrefixSuffix($classLoader);
		$this->testClassMap($classLoader);
	}

	public function finish() {
		$classLoader = $this->wire()->classLoader;
		foreach($this->namespaces as $namespace) {
			$classLoader->removeNamespace($namespace);
		}
		$this->cleanupFiles();
	}

	protected function testNamespaces(WireClassLoader $classLoader) {
		$namespace = $this->namespace . 'NS';
		$path1 = $this->path . 'namespace1/';
		$path2 = $this->path . 'namespace2/';
		$this->createClassFile($path1 . 'Alpha.php', $namespace, 'Alpha', 'alpha');
		$this->createClassFile($path2 . 'Beta.php', $namespace, 'Beta', 'beta');

		$classLoader->addNamespace($namespace, rtrim($path1, '/'));
		$classLoader->addNamespace($namespace, rtrim($path2, '/'));
		$this->namespaces[$namespace] = $namespace;

		$paths = $classLoader->getNamespace($namespace);
		$this->check('addNamespace() normalizes first path with trailing slash', $path1, $paths[0]);
		$this->check('addNamespace() registers multiple paths for one namespace', 2, count($paths));
		$this->check('hasNamespace() true for registered namespace', true, $classLoader->hasNamespace($namespace));

		$this->check('findClassFile() finds class in first namespace path', $path1 . 'Alpha.php', $classLoader->findClassFile($namespace . '\\Alpha'));
		$this->check('findClassFile() finds class in second namespace path', $path2 . 'Beta.php', $classLoader->findClassFile($namespace . '\\Beta'));

		$class = $namespace . '\\Alpha';
		$object = new $class();
		$this->check('autoload loads class from registered namespace', 'alpha', $object->value());

		$classLoader->removeNamespace($namespace, rtrim($path1, '/'));
		$paths = array_values($classLoader->getNamespace($namespace));
		$this->check('removeNamespace(namespace, path) removes normalized path', array($path2), $paths);
		$this->check('removeNamespace(namespace, path) keeps namespace when paths remain', true, $classLoader->hasNamespace($namespace));

		$classLoader->removeNamespace($namespace, rtrim($path2, '/'));
		$this->check('removeNamespace(namespace, path) removes namespace when last path removed', false, $classLoader->hasNamespace($namespace));
		unset($this->namespaces[$namespace]);

		$classLoader->removeNamespace($namespace, $path2);
		$this->check('removeNamespace(namespace, path) tolerates unknown namespace', false, $classLoader->hasNamespace($namespace));
	}

	protected function testExtensions(WireClassLoader $classLoader) {
		$namespace = $this->namespace . 'Ext';
		$path = $this->path . 'extensions/';
		$this->createClassFile($path . 'IncClass.inc', $namespace, 'IncClass', 'inc');

		$classLoader->addNamespace($namespace, $path);
		$this->namespaces[$namespace] = $namespace;

		$this->check('findClassFile() ignores unknown extension before addExtension()', false, $classLoader->findClassFile($namespace . '\\IncClass'));
		$classLoader->addExtension('inc');
		$this->check('addExtension() enables extension lookup without leading dot', $path . 'IncClass.inc', $classLoader->findClassFile($namespace . '\\IncClass'));
	}

	protected function testPrefixSuffix(WireClassLoader $classLoader) {
		$prefixPath = $this->path . 'prefix/';
		$suffixPath = $this->path . 'suffix/';
		$prefixClass = 'WireTestsPrefix' . getmypid();
		$suffixClass = 'WireTests' . getmypid() . 'Suffix';

		$this->createClassFile($prefixPath . "$prefixClass.php", __NAMESPACE__, $prefixClass, 'prefix');
		$this->createClassFile($suffixPath . "$suffixClass.php", __NAMESPACE__, $suffixClass, 'suffix');

		$classLoader->addPrefix('WireTestsPrefix', $prefixPath);
		$classLoader->addSuffix('Suffix', $suffixPath);

		$this->check('addPrefix() adds fallback lookup for matching class names', $prefixPath . "$prefixClass.php", $classLoader->findClassFile(__NAMESPACE__ . "\\$prefixClass"));
		$this->check('addSuffix() adds fallback lookup for matching class names', $suffixPath . "$suffixClass.php", $classLoader->findClassFile(__NAMESPACE__ . "\\$suffixClass"));
	}

	protected function testClassMap(WireClassLoader $classLoader) {
		$name1 = 'WireTestsMapped' . getmypid();
		$name2 = 'WireTestsNestedMapped' . getmypid();
		$file1 = $this->path . "mapped/$name1.php";
		$file2 = $this->path . "nested/$name2/$name2.php";

		$this->createClassFile($file1, __NAMESPACE__, $name1, 'mapped');
		$this->createClassFile($file2, __NAMESPACE__, $name2, 'nested');

		$classLoader->addClassMap(array(
			$name1 => 'mapped/',
			$name2 => 'nested>',
		), $this->path);

		$this->check('addClassMap() resolves directory entry to ClassName.php', $file1, $classLoader->findClassFile(__NAMESPACE__ . "\\$name1"));
		$this->check('addClassMap() resolves > entry to ClassName/ClassName.php', $file2, $classLoader->findClassFile(__NAMESPACE__ . "\\$name2"));

		$class = __NAMESPACE__ . "\\$name1";
		$object = new $class();
		$this->check('autoload loads class from class map', 'mapped', $object->value());
	}

	protected function createClassFile($filename, $namespace, $className, $returnValue) {
		$dir = dirname($filename);
		if(!is_dir($dir)) $this->wire()->files->mkdir($dir, true);
		$code = "<?php namespace $namespace;\n\nclass $className {\n\tpublic function value() { return '$returnValue'; }\n}\n";
		file_put_contents($filename, $code);
		return $filename;
	}

	protected function cleanupFiles() {
		if($this->path && is_dir($this->path)) {
			$this->wire()->files->rmdir($this->path, true);
		}
	}
}
