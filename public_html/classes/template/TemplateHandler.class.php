<?php
class TemplateHandler
{
	private $compiled_path = 'files/cache/template_compiled/';
	private $path = NULL;
	private $filename = NULL;
	private $file = NULL;
	private $xe_path = NULL;
	private $web_path = NULL;
	private $compiled_file = NULL;
	private $skipTags = NULL;
	private $handler_mtime = 0;
	static private $rootTpl = NULL;

	public function __construct() {
		$this->xe_path = rtrim(getScriptPath(), '/');
		$this->compiled_path = _XE_PATH_ . $this->compiled_path;
	}

	static public function &getInstance() {
		static $oTemplate = NULL;
		if(__DEBUG__ == 3) {
			if(!isset($GLOBALS['__TemplateHandlerCalled__'])) $GLOBALS['__TemplateHandlerCalled__'] = 1;
			else $GLOBALS['__TemplateHandlerCalled__']++;
		}
		if(!$oTemplate) $oTemplate = new TemplateHandler();
		return $oTemplate;
	}

	protected function init($tpl_path, $tpl_filename, $tpl_file = '') {
		if(substr($tpl_path, -1) != '/') $tpl_path .= '/';
		if(!is_dir($tpl_path)) return;
		if(!file_exists($tpl_path . $tpl_filename) && file_exists($tpl_path . $tpl_filename . '.html')) $tpl_filename .= '.html';
		if(!$tpl_file) $tpl_file = $tpl_path . $tpl_filename;
		$this->path = $tpl_path;
		$this->filename = $tpl_filename;
		$this->file = $tpl_file;
		$this->web_path = $this->xe_path . '/' . ltrim(preg_replace('@^' . preg_quote(_XE_PATH_, '@') . '|\./@', '', $this->path), '/');
		$hash = md5($this->file . __XE_VERSION__);
		$this->compiled_file = "{$this->compiled_path}{$hash}.compiled.php";
		$this->handler_mtime = filemtime(__FILE__);
		$skip = array('');
	}

	public function compile($tpl_path, $tpl_filename, $tpl_file = '') {
		$buff = false;
		if(__DEBUG__ == 3) $start = getMicroTime();
		$this->init($tpl_path, $tpl_filename, $tpl_file);
		if(!$this->file || !file_exists($this->file)) return "Err : '{$this->file}' template file does not exists.";
		if(is_null(self::$rootTpl)) self::$rootTpl = $this->file;
		$source_template_mtime = filemtime($this->file);
		$latest_mtime = $source_template_mtime > $this->handler_mtime ? $source_template_mtime : $this->handler_mtime;
		$oCacheHandler = CacheHandler::getInstance('template');
		if($oCacheHandler->isSupport()) {
			$cache_key = 'template:' . $this->file;
			$buff = $oCacheHandler->get($cache_key, $latest_mtime);
		} else {
			if(is_readable($this->compiled_file) && filemtime($this->compiled_file) > $latest_mtime && filesize($this->compiled_file)) {
				$buff = 'file://' . $this->compiled_file;
			}
		}
		if($buff === FALSE) {
			$buff = $this->parse();
			if($oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, $buff);
			else FileHandler::writeFile($this->compiled_file, $buff);
		}
		$output = $this->_fetch($buff);
		if($__templatehandler_root_tpl == $this->file) $__templatehandler_root_tpl = null;
		if(__DEBUG__ == 3) $GLOBALS['__template_elapsed__'] += getMicroTime() - $start;
		return $output;
	}

	public function compileDirect($tpl_path, $tpl_filename) {
		$this->init($tpl_path, $tpl_filename, null);
		if(!$this->file || !file_exists($this->file)) {
			Context::close();
			exit("Cannot find the template file: '{$this->file}'");
		}
		return $this->parse();
	}

	protected function parse($buff = null) {
		if(is_null($buff)) {
			if(!is_readable($this->file)) return;
			$buff = FileHandler::readFile($this->file);
		}
		if(is_null($this->skipTags)) $this->skipTags = array('marquee');
		$buff = preg_replace('@<!--//.*?-->@s', '', $buff);
		$buff = preg_replace_callback('/<(?:img|input|script)(?:[^<>]*?)(?(?=cond=")(?:cond="[^"]+"[^<>]*)+|)[^<>]* src="(?!(?:https?|file):\/\/|[\/\{])([^"]+)"/is', array($this, '_replacePath'), $buff);
		$buff = $this->_parseInline($buff);
		$buff = preg_replace_callback('/{(@[\s\S]+?|(?=\$\w+|_{1,2}[A-Z]+|[!\(+-]|\w+(?:\(|::)|\d+|[\'"].*?[\'"]).+?)}|<(!--[#%])?(include|import|(un)?load(?(4)|(?:_js_plugin)?))(?(2)\(["\']([^"\']+)["\'])(.*?)(?(2)\)--|\/)>|<!--(@[a-z@]*)([\s\S]*?)-->(\s*)/', array($this, '_parseResource'), $buff);
		$buff = preg_replace('@</?block\s*>@is', '', $buff);
		$temp = preg_replace_callback('/(<form(?:<\?php.+?\?>|[^<>]+)*?>)(.*?)(<\/form>)/is', array($this, '_compileFormAuthGeneration'), $buff);
		if($temp) $buff = $temp;
		$buff = '<?php if(!defined("__XE__"))exit;?>' . $buff;
		$buff = preg_replace(array('/(\n|\r\n)+/', '/(;)?( )*\?\>\<\?php([\n\t ]+)?/'), array("\n", ";\n"), $buff);
		return $buff;
	}

	private function _compileFormAuthGeneration($matches) {
		if($matches[1]) {
			preg_match('/ruleset="([^"]*?)"/is', $matches[1], $m);
			if($m[0]) {
				$matches[1] = preg_replace('/' . addcslashes($m[0], '?$') . '/i', '', $matches[1]);
				if(strpos($m[1], '@') !== FALSE) {
					$path = str_replace('@', '', $m[1]);
					$path = './files/ruleset/' . $path . '.xml';
				} else if(strpos($m[1], '#') !== FALSE) {
					$fileName = str_replace('#', '', $m[1]);
					$fileName = str_replace('<?php echo ', '', $fileName);
					$fileName = str_replace(' ?>', '', $fileName);
					$path = '#./files/ruleset/' . $fileName . '.xml';
					preg_match('@(?:^|\.?/)(modules/[\w-]+)@', $this->path, $mm);
					$module_path = $mm[1];
					list($rulsetFile) = explode('.', $fileName);
					$autoPath = $module_path . '/ruleset/' . $rulsetFile . '.xml';
					$m[1] = $rulsetFile;
				} else if(preg_match('@(?:^|\.?/)(modules/[\w-]+)@', $this->path, $mm)) {
					$module_path = $mm[1];
					$path = $module_path . '/ruleset/' . $m[1] . '.xml';
				}
				$matches[2] = '<input type="hidden" name="ruleset" value="' . $m[1] . '" />' . $matches[2];
				$matches[1] = '<?php Context::addJsFile("' . $path . '", FALSE, "", 0, "body", TRUE, "' . $autoPath . '") ?' . '>' . $matches[1];
			}
		}
		preg_match_all('/<input[^>]* name="(act|mid|vid)"/is', $matches[2], $m2);
		$checkVar = array('act', 'mid', 'vid');
		$resultArray = array_diff($checkVar, $m2[1]);
		if(is_array($resultArray)) {
			$generatedHidden = '';
			foreach($resultArray AS $key => $value) {
				$generatedHidden .= '<input type="hidden" name="' . $value . '" value="<?php echo $__Context->' . $value . ' ?>" />';
			}
			$matches[2] = $generatedHidden . $matches[2];
		}
		if(!preg_match('/no-error-return-url="true"/i', $matches[1])) {
			preg_match('/<input[^>]*name="error_return_url"[^>]*>/is', $matches[2], $m3);
			if(!$m3[0])
				$matches[2] = '<input type="hidden" name="error_return_url" value="<?php echo htmlspecialchars(getRequestUriByServerEnviroment(), ENT_COMPAT | ENT_HTML401, \'UTF-8\', false) ?>" />' . $matches[2];
		} else {
			$matches[1] = preg_replace('/no-error-return-url="true"/i', '', $matches[1]);
		}
		$matches[0] = '';
		return implode($matches);
	}

	private function _fetch($buff) {
		if(!$buff) return;
		$__Context = &$GLOBALS['__Context__'];
		$__Context->tpl_path = $this->path;
		if($_SESSION['is_logged']) $__Context->logged_info = Context::get('logged_info');
		$level = ob_get_level();
		ob_start();
		if(substr($buff, 0, 7) == 'file://') {
			if(__DEBUG__) {
				$eval_str = FileHandler::readFile(substr($buff, 7));
				$eval_str_buffed = "?>" . $eval_str;
				@eval($eval_str_buffed);
				$error_info = error_get_last();
				if ($error_info['type'] == 4) throw new Exception("Error Parsing Template - {$error_info['message']} in template file {$this->file}");
			} else {
				include(substr($buff, 7));
			}
		} else {
			$eval_str = "?>" . $buff;
			@eval($eval_str);
			$error_info = error_get_last();
			if ($error_info['type'] == 4) throw new Exception("Error Parsing Template - {$error_info['message']} in template file {$this->file}");
		}
		$contents = '';
		while (ob_get_level() - $level > 0) {
			$contents .= ob_get_contents();
			ob_end_clean();
		}
		return $contents;
	}

	private function _replacePath($match) {
		if(preg_match('@^\${@', $match[1])) return $match[0];
		if(preg_match('@^[\'|"]\s*\.\s*\$@', $match[1])) return $match[0];
		$src = preg_replace('@^(\./)+@', '', trim($match[1]));
		$src = $this->web_path . $src;
		$src = str_replace('/./', '/', $src);
		$src = preg_replace('@/((?:[\w-]+/)+)\1@', '/\1', $src);
		while(($tmp = preg_replace('@[^/]+/\.\./@', '', $src, 1)) !== $src) $src = $tmp;
		return substr($match[0], 0, -strlen($match[1]) - 6) . "src=\"{$src}\"";
	}

	private function _parseInline($buff) {
		if(preg_match_all('/<([a-zA-Z]+\d?)(?>(?!<[a-z]+\d?[\s>]).)*?(?:[ \|]cond| loop)="/s', $buff, $match) === false) return $buff;
		$tags = array_diff(array_unique($match[1]), $this->skipTags);
		if(!count($tags)) return $buff;
		$tags = '(?:' . implode('|', $tags) . ')';
		$split_regex = "@(<(?>/?{$tags})(?>[^<>\{\}\"']+|<!--.*?-->|{[^}]+}|\".*?\"|'.*?'|.)*?>)@s";
		$nodes = preg_split($split_regex, $buff, -1, PREG_SPLIT_DELIM_CAPTURE);
		$self_closing = array('area' => 1, 'base' => 1, 'basefont' => 1, 'br' => 1, 'hr' => 1, 'input' => 1, 'img' => 1, 'link' => 1, 'meta' => 1, 'param' => 1, 'frame' => 1, 'col' => 1);
		for($idx = 1, $node_len = count($nodes); $idx < $node_len; $idx+=2) {
			if(!($node = $nodes[$idx])) continue;
			if(preg_match_all('@\s(loop|cond)="([^"]+)"@', $node, $matches)) {
				$tag = substr($node, 1, strpos($node, ' ') - 1);
				$closing = 0;
				foreach($matches[1] as $n => $stmt) {
					$expr = $matches[2][$n];
					$expr = $this->_replaceVar($expr);
					$closing++;
					switch($stmt) {
						case 'cond':
							$nodes[$idx - 1] .= "<?php if({$expr}){ ?>";
						break;
						case 'loop':
							if(!preg_match('@^(?:(.+?)=>(.+?)(?:,(.+?))?|(.*?;.*?;.*?)|(.+?)\s*=\s*(.+?))$@', $expr, $expr_m)) break;
							if($expr_m[1]) {
								$expr_m[1] = trim($expr_m[1]);
								$expr_m[2] = trim($expr_m[2]);
								if($expr_m[3]) $expr_m[2] .= '=>' . trim($expr_m[3]);
								$nodes[$idx - 1] .= "<?php if({$expr_m[1]}&&count({$expr_m[1]}))foreach({$expr_m[1]} as {$expr_m[2]}){ ?>";
							} elseif($expr_m[4]) {
								$nodes[$idx - 1] .= "<?php for({$expr_m[4]}){ ?>";
							} elseif($expr_m[5]) {
								$nodes[$idx - 1] .= "<?php while({$expr_m[5]}={$expr_m[6]}){ ?>";
							}
						break;
					}
				}
				$node = preg_replace('@\s(loop|cond)="([^"]+)"@', '', $node);
				$close_php = '<?php ' . str_repeat('}', $closing) . ' ?>';
				if($node{1} == '!' || substr($node, -2, 1) == '/' || isset($self_closing[$tag])) {
					$nodes[$idx + 1] = $close_php . $nodes[$idx + 1];
				} else {
					$depth = 1;
					for($i = $idx + 2; $i < $node_len; $i+=2) {
						$nd = $nodes[$i];
						if(strpos($nd, $tag) === 1) {
							$depth++;
						} elseif(strpos($nd, '/' . $tag) === 1) {
							$depth--;
							if(!$depth) {
								$nodes[$i - 1] .= $nodes[$i] . $close_php;
								$nodes[$i] = '';
								break;
							}
						}
					}
				}
			}
			if(strpos($node, '|cond="') !== false) {
				$node = preg_replace('@(\s[-\w:]+(?:="[^"]+?")?)\|cond="(.+?)"@s', '<?php if($2){ ?>$1<?php } ?>', $node);
				$node = $this->_replaceVar($node);
			}
			if($nodes[$idx] != $node) $nodes[$idx] = $node;
		}
		$buff = implode('', $nodes);
		return $buff;
	}

	private function _parseResource($m) {
		if($m[1]) {
			if(preg_match('@^(\w+)\(@', $m[1], $mm) && !function_exists($mm[1])) return $m[0];
			$echo = 'echo ';
			if($m[1]{0} == '@') {
				$echo = '';
				$m[1] = substr($m[1], 1);
			}
			return '<?php ' . $echo . $this->_replaceVar($m[1]) . ' ?>';
		}
		if($m[3]) {
			$attr = array();
			if($m[5]) {
				if(preg_match_all('@,(\w+)="([^"]+)"@', $m[6], $mm)) {
					foreach($mm[1] as $idx => $name) $attr[$name] = $mm[2][$idx];
				}
				$attr['target'] = $m[5];
			} else {
				if(!preg_match_all('@ (\w+)="([^"]+)"@', $m[6], $mm)) return $m[0];
				foreach($mm[1] as $idx => $name) $attr[$name] = $mm[2][$idx];
			}
			switch($m[3]) {
				case 'include':
					if(!$this->file || !$attr['target']) return '';
					$pathinfo = pathinfo($attr['target']);
					$fileDir = $this->_getRelativeDir($pathinfo['dirname']);
					if(!$fileDir) return '';
					return "<?php \$__tpl=TemplateHandler::getInstance();echo \$__tpl->compile('{$fileDir}','{$pathinfo['basename']}') ?>";
				case 'load_js_plugin':
					$plugin = $this->_replaceVar($m[5]);
					$s = "<!--#JSPLUGIN:{$plugin}-->";
					if(strpos($plugin, '$__Context') === false) $plugin = "'{$plugin}'";
					$s .= "<?php Context::loadJavascriptPlugin({$plugin}); ?>";
					return $s;
				case 'import':
				case 'load':
				case 'unload':
					$metafile = '';
					$pathinfo = pathinfo($attr['target']);
					$doUnload = ($m[3] === 'unload');
					$isRemote = !!preg_match('@^(https?:)?//@i', $attr['target']);
					if(!$isRemote) {
						if(!preg_match('@^\.?/@', $attr['target'])) $attr['target'] = './' . $attr['target'];
						if(substr($attr['target'], -5) == '/lang') {
							$pathinfo['dirname'] .= '/lang';
							$pathinfo['basename'] = '';
							$pathinfo['extension'] = 'xml';
						}
						$relativeDir = $this->_getRelativeDir($pathinfo['dirname']);
						$attr['target'] = $relativeDir . '/' . $pathinfo['basename'];
					}
					switch($pathinfo['extension']) {
						case 'xml':
							if($isRemote || $doUnload) return '';
							if($pathinfo['basename'] == 'lang.xml' || substr($pathinfo['dirname'], -5) == '/lang') {
								$result = "Context::loadLang('{$relativeDir}');";
							} else {
								$result = "require_once('./classes/xml/XmlJsFilter.class.php');\$__xmlFilter=new XmlJsFilter('{$relativeDir}','{$pathinfo['basename']}');\$__xmlFilter->compile();";
							}
						break;
						case 'js':
							if($doUnload) {
								$result = "Context::unloadFile('{$attr['target']}','{$attr['targetie']}');";
							} else {
								$metafile = $attr['target'];
								$result = "\$__tmp=array('{$attr['target']}','{$attr['type']}','{$attr['targetie']}','{$attr['index']}');Context::loadFile(\$__tmp);unset(\$__tmp);";
							}
						break;
						case 'css':
							if($doUnload) {
								$result = "Context::unloadFile('{$attr['target']}','{$attr['targetie']}','{$attr['media']}');";
							} else {
								$metafile = $attr['target'];
								$result = "\$__tmp=array('{$attr['target']}','{$attr['media']}','{$attr['targetie']}','{$attr['index']}');Context::loadFile(\$__tmp);unset(\$__tmp);";
							}
						break;
					}
					$result = "<?php {$result} ?>";
					if($metafile) $result = "<!--#Meta:{$metafile}-->" . $result;
					return $result;
			}
		}
		if($m[7]) {
			$m[7] = substr($m[7], 1);
			if(!$m[7]) return '<?php ' . $this->_replaceVar($m[8]) . '{ ?>' . $m[9];
			if(!preg_match('/^(?:((?:end)?(?:if|switch|for(?:each)?|while)|end)|(else(?:if)?)|(break@)?(case|default)|(break))$/', $m[7], $mm)) return '';
			if($mm[1]) {
				if($mm[1]{0} == 'e') return '<?php } ?>' . $m[9];
				$precheck = '';
				if($mm[1] == 'switch') {
					$m[9] = '';
				} elseif($mm[1] == 'foreach') {
					$var = preg_replace('/^\s*\(\s*(.+?) .*$/', '$1', $m[8]);
					$precheck = "if({$var}&&count({$var}))";
				}
				return '<?php ' . $this->_replaceVar($precheck . $m[7] . $m[8]) . '{ ?>' . $m[9];
			}
			if($mm[2]) return "<?php }{$m[7]}" . $this->_replaceVar($m[8]) . "{ ?>" . $m[9];
			if($mm[4]) return "<?php " . ($mm[3] ? 'break;' : '') . "{$m[7]} " . trim($m[8], '()') . ": ?>" . $m[9];
			if($mm[5]) return "<?php break; ?>";
			return '';
		}
		return $m[0];
	}

	function _getRelativeDir($path) {
		$_path = $path;
		$fileDir = strtr(realpath($this->path), '\\', '/');
		if($path{0} != '/') $path = strtr(realpath($fileDir . '/' . $path), '\\', '/');
		if(!$path) {
			$dirs = explode('/', $fileDir);
			$paths = explode('/', $_path);
			$idx = array_search($paths[0], $dirs);
			if($idx !== false) {
				while($dirs[$idx] && $dirs[$idx] === $paths[0]) {
					array_splice($dirs, $idx, 1);
					array_shift($paths);
				}
				$path = strtr(realpath($fileDir . '/' . implode('/', $paths)), '\\', '/');
			}
		}
		$path = preg_replace('/^' . preg_quote(_XE_PATH_, '/') . '/', '', $path);
		return $path;
	}

	function _replaceVar($php) {
		if(!strlen($php)) return '';
		return preg_replace('@(?<!::|\\\\|(?<!eval\()\')\$([a-z]|_[a-z0-9])@i', '\$__Context->$1', $php);
	}
}
