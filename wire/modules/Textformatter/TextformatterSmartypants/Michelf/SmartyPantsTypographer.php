<?php
#
# SmartyPants Typographer  -  Smart typography for web sites
#
# PHP SmartyPants & Typographer  
# Copyright (c) 2004-2016 Michel Fortin
# <https://michelf.ca/>
#
# Original SmartyPants
# Copyright (c) 2003-2004 John Gruber
# <https://daringfireball.net/>
#
namespace Michelf;


#
# SmartyPants Typographer Parser Class
#
class SmartyPantsTypographer extends \Michelf\SmartyPants {

	### Configuration Variables ###

	# Options to specify which transformations to make:
	public $do_comma_quotes      = 0;
	public $do_guillemets        = 0;
	public $do_space_emdash      = 0;
	public $do_space_endash      = 0;
	public $do_space_colon       = 0;
	public $do_space_semicolon   = 0;
	public $do_space_marks       = 0;
	public $do_space_frenchquote = 0;
	public $do_space_thousand    = 0;
	public $do_space_unit        = 0;
	
	# Smart quote characters:
	# Opening and closing smart double-quotes.
	public $smart_doublequote_open  = '&#8220;';
	public $smart_doublequote_close = '&#8221;';
	public $smart_singlequote_open  = '&#8216;';
	public $smart_singlequote_close = '&#8217;'; # Also apostrophe.

	# Space characters for different places:
	# Space around em-dashes.  "He_—_or she_—_should change that."
	public $space_emdash      = " ";
	# Space around en-dashes.  "He_–_or she_–_should change that."
	public $space_endash      = " ";
	# Space before a colon. "He said_: here it is."
	public $space_colon       = "&#160;";
	# Space before a semicolon. "That's what I said_; that's what he said."
	public $space_semicolon   = "&#160;";
	# Space before a question mark and an exclamation mark: "¡_Holà_! What_?"
	public $space_marks       = "&#160;";
	# Space inside french quotes. "Voici la «_chose_» qui m'a attaqué."
	public $space_frenchquote = "&#160;";
	# Space as thousand separator. "On compte 10_000 maisons sur cette liste."
	public $space_thousand    = "&#160;";
	# Space before a unit abreviation. "This 12_kg of matter costs 10_$."
	public $space_unit        = "&#160;";
	
	# Expression of a space (breakable or not):
	public $space = '(?: | |&nbsp;|&#0*160;|&#x0*[aA]0;)';


	### Parser Implementation ###

	public function __construct($attr = SmartyPants::ATTR_DEFAULT) {
	#
	# Initialize a SmartyPantsTypographer_Parser with certain attributes.
	#
	# Parser attributes:
	# 0 : do nothing
	# 1 : set all, except dash spacing
	# 2 : set all, except dash spacing, using old school en- and em- dash shortcuts
	# 3 : set all, except dash spacing, using inverted old school en and em- dash shortcuts
	# 
	# Punctuation:
	# q -> quotes
	# b -> backtick quotes (``double'' only)
	# B -> backtick quotes (``double'' and `single')
	# c -> comma quotes (,,double`` only)
	# g -> guillemets (<<double>> only)
	# d -> dashes
	# D -> old school dashes
	# i -> inverted old school dashes
	# e -> ellipses
	# w -> convert &quot; entities to " for Dreamweaver users
	#
	# Spacing:
	# : -> colon spacing +-
	# ; -> semicolon spacing +-
	# m -> question and exclamation marks spacing +-
	# h -> em-dash spacing +-
	# H -> en-dash spacing +-
	# f -> french quote spacing +-
	# t -> thousand separator spacing -
	# u -> unit spacing +-
	#   (you can add a plus sign after some of these options denoted by + to 
	#    add the space when it is not already present, or you can add a minus 
	#    sign to completly remove any space present)
	#
		# Initialize inherited SmartyPants parser.
		parent::__construct($attr);
				
		if ($attr == "1" || $attr == "2" || $attr == "3") {
			# Do everything, turn all options on.
			$this->do_comma_quotes      = 1;
			$this->do_guillemets  = 1;
			$this->do_space_emdash      = 1;
			$this->do_space_endash      = 1;
			$this->do_space_colon       = 1;
			$this->do_space_semicolon   = 1;
			$this->do_space_marks       = 1;
			$this->do_space_frenchquote = 1;
			$this->do_space_thousand    = 1;
			$this->do_space_unit        = 1;
		}
		else if ($attr == "-1") {
			# Special "stupefy" mode.
			$this->do_stupefy   = 1;
		}
		else {
			$chars = preg_split('//', $attr);
			foreach ($chars as $c){
				if      ($c == "c") { $current =& $this->do_comma_quotes; }
				else if ($c == "g") { $current =& $this->do_guillemets; }
				else if ($c == ":") { $current =& $this->do_space_colon; }
				else if ($c == ";") { $current =& $this->do_space_semicolon; }
				else if ($c == "m") { $current =& $this->do_space_marks; }
				else if ($c == "h") { $current =& $this->do_space_emdash; }
				else if ($c == "H") { $current =& $this->do_space_endash; }
				else if ($c == "f") { $current =& $this->do_space_frenchquote; }
				else if ($c == "t") { $current =& $this->do_space_thousand; }
				else if ($c == "u") { $current =& $this->do_space_unit; }
				else if ($c == "+") {
					$current = 2;
					unset($current);
				}
				else if ($c == "-") {
					$current = -1;
					unset($current);
				}
				else {
					# Unknown attribute option, ignore.
				}
				$current = 1;
			}
		}
	}


	function decodeEntitiesInConfiguration() {
	#
	#   Utility function that converts entities in configuration variables to
	#   UTF-8 characters.
	#
		$this->smart_doublequote_open  = html_entity_decode($this->smart_doublequote_open);
		$this->smart_doublequote_close = html_entity_decode($this->smart_doublequote_close);
		$this->smart_singlequote_open  = html_entity_decode($this->smart_singlequote_open);
		$this->smart_singlequote_close = html_entity_decode($this->smart_singlequote_close);
		$this->space_emdash      = html_entity_decode($this->space_emdash);
		$this->space_endash      = html_entity_decode($this->space_endash);
		$this->space_colon       = html_entity_decode($this->space_colon);
		$this->space_semicolon   = html_entity_decode($this->space_semicolon);
		$this->space_marks       = html_entity_decode($this->space_marks);
		$this->space_frenchquote = html_entity_decode($this->space_frenchquote);
		$this->space_thousand    = html_entity_decode($this->space_thousand);
		$this->space_unit        = html_entity_decode($this->space_unit);
	}


	function educate($t, $prev_token_last_char) {
		$t = parent::educate($t, $prev_token_last_char);
		
		if ($this->do_comma_quotes)      $t = $this->educateCommaQuotes($t);
		if ($this->do_guillemets)        $t = $this->educateGuillemets($t);
		
		if ($this->do_space_emdash)      $t = $this->spaceEmDash($t);
		if ($this->do_space_endash)      $t = $this->spaceEnDash($t);
		if ($this->do_space_colon)       $t = $this->spaceColon($t);
		if ($this->do_space_semicolon)   $t = $this->spaceSemicolon($t);
		if ($this->do_space_marks)       $t = $this->spaceMarks($t);
		if ($this->do_space_frenchquote) $t = $this->spaceFrenchQuotes($t);
		if ($this->do_space_thousand)    $t = $this->spaceThousandSeparator($t);
		if ($this->do_space_unit)        $t = $this->spaceUnit($t);
		
		return $t;
	}


	protected function educateQuotes($_) {
	#
	#   Parameter:  String.
	#
	#   Returns:    The string, with "educated" curly quote HTML entities.
	#
	#   Example input:  "Isn't this fun?"
	#   Example output: &#8220;Isn&#8217;t this fun?&#8221;
	#
		$dq_open  = $this->smart_doublequote_open;
		$dq_close = $this->smart_doublequote_close;
		$sq_open  = $this->smart_singlequote_open;
		$sq_close = $this->smart_singlequote_close;
	
		# Make our own "punctuation" character class, because the POSIX-style
		# [:PUNCT:] is only available in Perl 5.6 or later:
		$punct_class = "[!\"#\\$\\%'()*+,-.\\/:;<=>?\\@\\[\\\\\]\\^_`{|}~]";

		# Special case if the very first character is a quote
		# followed by punctuation at a non-word-break. Close the quotes by brute force:
		$_ = preg_replace(
			array("/^'(?=$punct_class\\B)/", "/^\"(?=$punct_class\\B)/"),
			array($sq_close,                 $dq_close), $_);

		# Special case for double sets of quotes, e.g.:
		#   <p>He said, "'Quoted' words in a larger quote."</p>
		$_ = preg_replace(
			array("/\"'(?=\w)/",     "/'\"(?=\w)/"),
			array($dq_open.$sq_open, $sq_open.$dq_open), $_);

		# Special case for decade abbreviations (the '80s):
		$_ = preg_replace("/'(?=\\d{2}s)/", $sq_close, $_);

		$close_class = '[^\ \t\r\n\[\{\(\-]';
		$dec_dashes = '&\#8211;|&\#8212;';

		# Get most opening single quotes:
		$_ = preg_replace("{
			(
				\\s          |   # a whitespace char, or
				&nbsp;      |   # a non-breaking space entity, or
				--          |   # dashes, or
				&[mn]dash;  |   # named dash entities
				$dec_dashes |   # or decimal entities
				&\\#x201[34];    # or hex
			)
			'                   # the quote
			(?=\\w)              # followed by a word character
			}x", '\1'.$sq_open, $_);
		# Single closing quotes:
		$_ = preg_replace("{
			($close_class)?
			'
			(?(1)|          # If $1 captured, then do nothing;
			  (?=\\s | s\\b)  # otherwise, positive lookahead for a whitespace
			)               # char or an 's' at a word ending position. This
							# is a special case to handle something like:
							# \"<i>Custer</i>'s Last Stand.\"
			}xi", '\1'.$sq_close, $_);

		# Any remaining single quotes should be opening ones:
		$_ = str_replace("'", $sq_open, $_);


		# Get most opening double quotes:
		$_ = preg_replace("{
			(
				\\s          |   # a whitespace char, or
				&nbsp;      |   # a non-breaking space entity, or
				--          |   # dashes, or
				&[mn]dash;  |   # named dash entities
				$dec_dashes |   # or decimal entities
				&\\#x201[34];    # or hex
			)
			\"                   # the quote
			(?=\\w)              # followed by a word character
			}x", '\1'.$dq_open, $_);

		# Double closing quotes:
		$_ = preg_replace("{
			($close_class)?
			\"
			(?(1)|(?=\\s))   # If $1 captured, then do nothing;
							   # if not, then make sure the next char is whitespace.
			}x", '\1'.$dq_close, $_);

		# Any remaining quotes should be opening ones.
		$_ = str_replace('"', $dq_open, $_);

		return $_;
	}


	protected function educateCommaQuotes($_) {
	#
	#   Parameter:  String.
	#   Returns:    The string, with ,,comma,, -style double quotes
	#               translated into HTML curly quote entities.
	#
	#   Example input:  ,,Isn't this fun?,,
	#   Example output: &#8222;Isn't this fun?&#8222;
	#
	# Note: this is meant to be used alongside with backtick quotes; there is 
	# no language that use only lower quotations alone mark like in the example.
	#
		$_ = str_replace(",,", '&#8222;', $_);
		return $_;
	}


	protected function educateGuillemets($_) {
	#
	#   Parameter:  String.
	#   Returns:    The string, with << guillemets >> -style quotes
	#               translated into HTML guillemets entities.
	#
	#   Example input:  << Isn't this fun? >>
	#   Example output: &#8222; Isn't this fun? &#8222;
	#
		$_ = preg_replace("/(?:<|&lt;){2}/", '&#171;', $_);
		$_ = preg_replace("/(?:>|&gt;){2}/", '&#187;', $_);
		return $_;
	}


	protected function spaceFrenchQuotes($_) {
	#
	#	Parameters: String, replacement character, and forcing flag.
	#	Returns:    The string, with appropriates spaces replaced 
	#				inside french-style quotes, only french quotes.
	#
	#	Example input:  Quotes in « French », »German« and »Finnish» style.
	#	Example output: Quotes in «_French_», »German« and »Finnish» style.
	#
		$opt = ( $this->do_space_frenchquote ==  2 ? '?' : '' );
		$chr = ( $this->do_space_frenchquote != -1 ? $this->space_frenchquote : '' );
		
		# Characters allowed immediatly outside quotes.
		$outside_char = $this->space . '|\s|[.,:;!?\[\](){}|@*~=+-]|¡|¿';
		
		$_ = preg_replace(
			"/(^|$outside_char)(&#171;|«|&#8250;|‹)$this->space$opt/",
			"\\1\\2$chr", $_);
		$_ = preg_replace(
			"/$this->space$opt(&#187;|»|&#8249;|›)($outside_char|$)/", 
			"$chr\\1\\2", $_);
		return $_;
	}


	protected function spaceColon($_) {
	#
	#	Parameters: String, replacement character, and forcing flag.
	#	Returns:    The string, with appropriates spaces replaced 
	#				before colons.
	#
	#	Example input:  Ingredients : fun.
	#	Example output: Ingredients_: fun.
	#
		$opt = ( $this->do_space_colon ==  2 ? '?' : '' );
		$chr = ( $this->do_space_colon != -1 ? $this->space_colon : '' );
		
		$_ = preg_replace("/$this->space$opt(:)(\\s|$)/m",
						  "$chr\\1\\2", $_);
		return $_;
	}


	protected function spaceSemicolon($_) {
	#
	#	Parameters: String, replacement character, and forcing flag.
	#	Returns:    The string, with appropriates spaces replaced 
	#				before semicolons.
	#
	#	Example input:  There he goes ; there she goes.
	#	Example output: There he goes_; there she goes.
	#
		$opt = ( $this->do_space_semicolon ==  2 ? '?' : '' );
		$chr = ( $this->do_space_semicolon != -1 ? $this->space_semicolon : '' );
		
		$_ = preg_replace("/$this->space(;)(?=\\s|$)/m", 
						  " \\1", $_);
		$_ = preg_replace("/((?:^|\\s)(?>[^&;\\s]+|&#?[a-zA-Z0-9]+;)*)".
						  " $opt(;)(?=\\s|$)/m", 
						  "\\1$chr\\2", $_);
		return $_;
	}


	protected function spaceMarks($_) {
	#
	#	Parameters: String, replacement character, and forcing flag.
	#	Returns:    The string, with appropriates spaces replaced 
	#				around question and exclamation marks.
	#
	#	Example input:  ¡ Holà ! What ?
	#	Example output: ¡_Holà_! What_?
	#
		$opt = ( $this->do_space_marks ==  2 ? '?' : '' );
		$chr = ( $this->do_space_marks != -1 ? $this->space_marks : '' );

		// Regular marks.
		$_ = preg_replace("/$this->space$opt([?!]+)/", "$chr\\1", $_);

		// Inverted marks.
		$imarks = "(?:¡|&iexcl;|&#161;|&#x[Aa]1;|¿|&iquest;|&#191;|&#x[Bb][Ff];)";
		$_ = preg_replace("/($imarks+)$this->space$opt/", "\\1$chr", $_);
	
		return $_;
	}


	protected function spaceEmDash($_) {
	#
	#	Parameters: String, two replacement characters separated by a hyphen (`-`),
	#				and forcing flag.
	#
	#	Returns:    The string, with appropriates spaces replaced 
	#				around dashes.
	#
	#	Example input:  Then — without any plan — the fun happend.
	#	Example output: Then_—_without any plan_—_the fun happend.
	#
		$opt = ( $this->do_space_emdash ==  2 ? '?' : '' );
		$chr = ( $this->do_space_emdash != -1 ? $this->space_emdash : '' );
		$_ = preg_replace("/$this->space$opt(&#8212;|—)$this->space$opt/", 
			"$chr\\1$chr", $_);
		return $_;
	}
	
	
	protected function spaceEnDash($_) {
	#
	#	Parameters: String, two replacement characters separated by a hyphen (`-`),
	#				and forcing flag.
	#
	#	Returns:    The string, with appropriates spaces replaced 
	#				around dashes.
	#
	#	Example input:  Then — without any plan — the fun happend.
	#	Example output: Then_—_without any plan_—_the fun happend.
	#
		$opt = ( $this->do_space_endash ==  2 ? '?' : '' );
		$chr = ( $this->do_space_endash != -1 ? $this->space_endash : '' );
		$_ = preg_replace("/$this->space$opt(&#8211;|–)$this->space$opt/", 
			"$chr\\1$chr", $_);
		return $_;
	}


	protected function spaceThousandSeparator($_) {
	#
	#	Parameters: String, replacement character, and forcing flag.
	#	Returns:    The string, with appropriates spaces replaced 
	#				inside numbers (thousand separator in french).
	#
	#	Example input:  Il y a 10 000 insectes amusants dans ton jardin.
	#	Example output: Il y a 10_000 insectes amusants dans ton jardin.
	#
		$chr = ( $this->do_space_thousand != -1 ? $this->space_thousand : '' );
		$_ = preg_replace('/([0-9]) ([0-9])/', "\\1$chr\\2", $_);
		return $_;
	}


	protected $units = '
		### Metric units (with prefixes)
		(?:
			p |
			µ | &micro; | &\#0*181; | &\#[xX]0*[Bb]5; |
			[mcdhkMGT]
		)?
		(?:
			[mgstAKNJWCVFSTHBL]|mol|cd|rad|Hz|Pa|Wb|lm|lx|Bq|Gy|Sv|kat|
			Ω | Ohm | &Omega; | &\#0*937; | &\#[xX]0*3[Aa]9;
		)|
		### Computers units (KB, Kb, TB, Kbps)
		[kKMGT]?(?:[oBb]|[oBb]ps|flops)|
		### Money
		¢ | &cent; | &\#0*162; | &\#[xX]0*[Aa]2; |
		M?(?:
			£ | &pound; | &\#0*163; | &\#[xX]0*[Aa]3; |
			¥ | &yen;   | &\#0*165; | &\#[xX]0*[Aa]5; |
			€ | &euro;  | &\#0*8364; | &\#[xX]0*20[Aa][Cc]; |
			$
		)|
		### Other units
		(?: ° | &deg; | &\#0*176; | &\#[xX]0*[Bb]0; ) [CF]? | 
		%|pt|pi|M?px|em|en|gal|lb|[NSEOW]|[NS][EOW]|ha|mbar
		'; //x

	protected function spaceUnit($_) {
	#
	#	Parameters: String, replacement character, and forcing flag.
	#	Returns:    The string, with appropriates spaces replaced
	#				before unit symbols.
	#
	#	Example input:  Get 3 mol of fun for 3 $.
	#	Example output: Get 3_mol of fun for 3_$.
	#
		$opt = ( $this->do_space_unit ==  2 ? '?' : '' );
		$chr = ( $this->do_space_unit != -1 ? $this->space_unit : '' );

		$_ = preg_replace('/
			(?:([0-9])[ ]'.$opt.') # Number followed by space.
			('.$this->units.')     # Unit.
			(?![a-zA-Z0-9])  # Negative lookahead for other unit characters.
			/x',
			"\\1$chr\\2", $_);

		return $_;
	}


	protected function spaceAbbr($_) {
	#
	#	Parameters: String, replacement character, and forcing flag.
	#	Returns:    The string, with appropriates spaces replaced
	#				around abbreviations.
	#
	#	Example input:  Fun i.e. something pleasant.
	#	Example output: Fun i.e._something pleasant.
	#
		$opt = ( $this->do_space_abbr ==  2 ? '?' : '' );
		
		$_ = preg_replace("/(^|\s)($this->abbr_after) $opt/m",
			"\\1\\2$this->space_abbr", $_);
		$_ = preg_replace("/( )$opt($this->abbr_sp_before)(?![a-zA-Z'])/m", 
			"\\1$this->space_abbr\\2", $_);
		return $_;
	}


	protected function stupefyEntities($_) {
	#
	#   Adding angle quotes and lower quotes to SmartyPants's stupefy mode.
	#
		$_ = parent::stupefyEntities($_);

		$_ = str_replace(array('&#8222;', '&#171;', '&#187'), '"', $_);

		return $_;
	}


	protected function processEscapes($_) {
	#
	#   Adding a few more escapes to SmartyPants's escapes:
	#
	#               Escape  Value
	#               ------  -----
	#               \,      &#44;
	#               \<      &#60;
	#               \>      &#62;
	#
		$_ = parent::processEscapes($_);

		$_ = str_replace(
			array('\,',    '\<',    '\>',    '\&lt;', '\&gt;'),
			array('&#44;', '&#60;', '&#62;', '&#60;', '&#62;'), $_);

		return $_;
	}
}
