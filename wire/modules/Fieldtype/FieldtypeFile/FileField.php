<?php namespace ProcessWire;

/**
 * File Field (for FieldtypeFile)
 *
 * Configured with FieldtypeFile
 * ==============================
 * @property string $extensions Space-separated list of allowed file extensions (e.g. 'pdf doc docx').
 * @property array $okExtensions File extensions whitelisted to allow without a file validator module. Optional. (default=[])
 * @property int $maxFiles Maximum number of files allowed (0=no limit, default=0).
 * @property int $outputFormat How the field value is formatted for output:
 *   0=auto (Pagefiles or Pagefile/null based on maxFiles setting), 1=Pagefiles instance, 2=Pagefile instance or null, 30=string.
 *   Use FieldtypeFile::outputFormatAuto, ::outputFormatArray, ::outputFormatSingle, ::outputFormatString constants.
 * @property string $outputString Template string used when outputFormat=30 (string), e.g. '<a href="{url}">{description}</a>'.
 * @property int $useTags Enable file tags: 0=off, 1=text input, 8=predefined only, 9=predefined + text input.
 *   Use FieldtypeFile::useTagsOff, ::useTagsNormal, ::useTagsPredefined constants.
 * @property string $tagsList Space-separated predefined tags (when useTags includes predefined).
 * @property array $textformatters Text formatters (Textformatter module names) applied to file descriptions (e.g. ['TextformatterEntities']).
 * @property int $defaultValuePage Page ID whose files are used as a fallback when this field is empty (default=0).
 * @property string $inputfieldClass Inputfield class (Inputfield module name) for this field (default='InputfieldFile').
 *
 * Configured with InputfieldFile
 * ==============================
 * @property bool|int $unzip Decompress uploaded ZIP files and add their contents? (default=false).
 * @property bool|int $overwrite Overwrite existing files with the same name on upload? (default=false).
 * @property int $descriptionRows Number of rows for the file description input. When 1 uses single line text input. When 2+ uses multi-line textarea input. (0=disable, default=1).
 * @property bool|int $noLang Disable multi-language support for file descriptions? Applies only if LanguageSupport module is installed. (default=false).
 *
 * @since 3.0.258
 *
 */
class FileField extends Field {
}
