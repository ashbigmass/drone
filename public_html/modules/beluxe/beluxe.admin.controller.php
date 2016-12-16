<?php
class beluxeAdminController extends beluxe
{
	function init() {
	}

	function _setLocation($a_modsrl, $act) {
		$is_poped = (int)Context::get('is_poped');
		$retUrl = Context::get('success_return_url');
		if (!$retUrl) {
			$module = Context::get('module');
			$retUrl = getNotEncodedUrl(
				'', $module ? 'module' : 'mid', $module ? $module : Context::get('mid'),
				'module_srl', (int) $a_modsrl ? $a_modsrl : '', 'act', $act
			);
		}
		if (!$retUrl) $retUrl = Context::get('error_return_url');
		if (in_array(Context::getRequestMethod(), array('XMLRPC', 'JSON', 'JS_CALLBACK'))) {
			$this->add('is_modal', (int)$is_modal ? '1' : '');
			$this->add('url', $retUrl);
		} else {
			$this->setRedirectUrl($retUrl);
			if ($is_poped) {
				$msg_code = $this->getMessage();
				htmlHeader();
				if ($msg_code) alertScript(Context::getLang($msg_code));
				reload(true);
				closePopupScript();
				htmlFooter();
				Context::close();
				exit;
			}
		}
	}

	function _setModuleInfo($a_modsrl) {
		$arglst = func_get_args();
		if (!$a_modsrl || count($arglst) < 3) return;
		array_shift($arglst);
		$cmModule = &getModel('module');
		$ccModule = &getController('module');
		$args = $cmModule->getModuleInfoByModuleSrl($a_modsrl);
		for ($i = 0, $cnt = count($arglst); $i < $cnt; $i+= 2) $args->{$arglst[$i]} = $arglst[$i + 1];
		$ccModule->updateModule($args);
	}

	function _setModulePartConfig($a_modsrl, $a_cfg) {
		if (!count($a_cfg)) return;
		$ccModule = &getController('module');
		$cmModule = &getModel('module');
		foreach ($a_cfg as $tk => $tv) {
			$doc_cfg = $cmModule->getModulePartConfig($tk, $a_modsrl);
			foreach ($tv as $tk2 => $tv2) $doc_cfg->{$tk2} = $tv2;
			$ccModule->insertModulePartConfig($tk, $a_modsrl, $doc_cfg);
		}
	}

	function _deleteCacheHandler($a_modsrl, $a_okeys) {
		$oCacheNew = CacheHandler::getInstance('object', NULL, TRUE);
		if ($oCacheNew->isSupport()) {
			foreach ($a_okeys as $val) {
				$object_key = 'module_' . $val . ':' . $a_modsrl;
				$cache_key = $oCacheNew->getGroupKey('site_and_module', $object_key);
				$oCacheNew->delete($cache_key);
			}
		}
	}

	function doDeleteCategory($a_catesrl) {
		$oThisModel = &getModel(__XEFM_NAME__);
		$cmDocument = &getModel('document');
		$oCateIfo = $cmDocument->getCategory($a_catesrl, array('module_srl'));
		if (!$oCateIfo->module_srl) return new Object(-1, 'msg_invalid_request');
		$args->category_srl = $a_catesrl;
		$out = executeQuery('document.getChildCategoryCount', $args);
		if (!$out->toBool()) return $out;
		if ((int)$out->data->count > 0) return new Object(-1, 'msg_cannot_delete_for_child');
		$tar_cate_srl = 0;
		unset($args);
		$args->target_category_srl = $tar_cate_srl;
		$args->source_category_srl = $a_catesrl;
		$out = executeQuery('document.updateDocumentCategory', $args);
		if (!$out->toBool()) return $out;
		unset($args);
		$args->module_srl = $oCateIfo->module_srl;
		$args->category_srl = $tar_cate_srl;
		$out = executeQuery('document.getDocumentCount', $args);
		$args->document_count = (int)$out->data->count;
		$out = executeQuery('document.updateCategoryCount', $args);
		if (!$out->toBool()) return $out;
		unset($args);
		$args->category_srl = $a_catesrl;
		return executeQuery('document.deleteCategory', $args);
	}

	function doInsertCategory($aObj) {
		if ($aObj->parent_srl) {
			$cmDocument = &getModel('document');
			$oParCate = $cmDocument->getCategory($aObj->parent_srl, array('module_srl', 'category_srl', 'list_order'));
			if ($aObj->parent_srl != $oParCate->category_srl) return new Object(-1, 'msg_invalid_request');
		} else {
		}
		if (!$aObj->category_srl) $aObj->category_srl = getNextSequence();
		$out = executeQuery('document.insertCategory', $aObj);
		if ($out->toBool()) $out->add('category_srl', $aObj->category_srl);
		return $out;
	}

	function doUpdateCategory($aObj) {
		return executeQuery('document.updateCategory', $aObj);
	}

	function procBeluxeAdminInsert() {
		function __beluxe_checkArgs($chks) {
			$order = explode(',', __XEFM_ORDER__);
			if (isset($chks->use_anonymous) && !in_array($chks->use_anonymous, array('Y', 'S'))) $chks->use_anonymous = 'N';
			if (isset($chks->use_history) && !in_array($chks->use_history, array('Y', 'Trace'))) $chks->use_history = 'N';
			if (isset($chks->consultation) && $chks->consultation != 'Y') $chks->consultation = 'N';
			if (isset($chks->schedule_document_register) && $chks->schedule_document_register != 'Y') $chks->schedule_document_register = 'N';
			if (isset($chks->use_mobile_uploader) && $chks->use_mobile_uploader != 'Y') $chks->use_mobile_uploader = 'N';
			if (isset($chks->use_title_color) && $chks->use_title_color != 'Y') $chks->use_title_color = 'N';
			if (isset($chks->category_trace) && $chks->category_trace != 'Y') $chks->category_trace = 'N';
			if (isset($chks->use_trash) && $chks->use_trash != 'Y') $chks->use_trash = 'N';
			if (isset($chks->use_best) && $chks->use_best != 'Y') $chks->use_best = 'N';
			if (isset($chks->use_c_best) && $chks->use_c_best != 'Y') $chks->use_c_best = 'N';
			if (isset($chks->use_blind) && $chks->use_blind != 'Y') $chks->use_blind = 'N';
			if (isset($chks->use_c_blind) && $chks->use_c_blind != 'Y') $chks->use_c_blind = 'N';
			if (isset($chks->best_voted)) $chks->best_voted = (int)$chks->best_voted;
			if (isset($chks->best_c_voted)) $chks->best_c_voted = (int)$chks->best_c_voted;
			if (isset($chks->best_date)) $chks->best_date = (int)$chks->best_date;
			if (isset($chks->best_c_date)) $chks->best_c_date = (int)$chks->best_c_date;
			if (isset($chks->best_count)) $chks->best_count = (int)$chks->best_count;
			if (isset($chks->best_c_count)) $chks->best_c_count = (int)$chks->best_c_count;
			if (isset($chks->blind_voted)) $chks->blind_voted = (int)$chks->blind_voted;
			if (isset($chks->blind_c_voted)) $chks->blind_c_voted = (int)$chks->blind_c_voted;
			if (isset($chks->use_status) && is_array($chks->use_status)) $chks->use_status = implode(',', $chks->use_status);
			if (isset($chks->use_c_status) && is_array($chks->use_c_status)) $chks->use_c_status = implode(',', $chks->use_c_status);
			$custom_status = explode(',', $chks->custom_status);
			$chks->custom_status = array();
			foreach ($custom_status as $val) {
				$val = trim($val);
				if (!$val) continue;
				$chks->custom_status[] = substr($val, 0, 100);
				if (count($chks->custom_status) > 8) break;
			}
			$chks->custom_status = implode(',', $chks->custom_status);
			if (isset($chks->use_point_percent)) $chks->use_point_percent = (int)$chks->use_point_percent;
			if (isset($chks->use_point_type) && !in_array($chks->use_point_type, array('R', 'A'))) $chks->use_point_type = 'R';
			if (isset($chks->use_restrict_view) && !in_array($chks->use_restrict_view, array('Y', 'P'))) $chks->use_restrict_view = 'N';
			if (isset($chks->use_restrict_down) && !in_array($chks->use_restrict_down, array('Y', 'P'))) $chks->use_restrict_down = 'N';
			if (isset($chks->use_lock_document) && !in_array($chks->use_lock_document, array('Y', 'T', 'C'))) $chks->use_lock_document = 'N';
			if (isset($chks->use_lock_document_option)) $chks->use_lock_document_option = (int)$chks->use_lock_document_option;
			if (isset($chks->use_lock_comment) && !in_array($chks->use_lock_comment, array('Y', 'T', 'C'))) $chks->use_lock_comment = 'N';
			if (isset($chks->use_lock_comment_option)) $chks->use_lock_comment_option = (int)$chks->use_lock_comment_option;
			return $chks;
		}
		$args = Context::getRequestVars();
		$mid_list = explode(',', $args->target_module_mids ? $args->target_module_mids : $args->module_mid);
		if (!count($mid_list)) return new Object(-1, 'msg_invalid_request');
		$arr_up = array('default_category_title');
		if ($args->target_module_mids) {
			unset($args->target_module_mids);
			$arr_up[] = 'mid';
			$arr_up[] = 'module_category_srl';
			$arr_up[] = 'browser_title';
			$arr_up[] = 'skin';
			$arr_up[] = 'default_type';
			$arr_up[] = 'default_type_option';
			$arr_up[] = 'description';
			$arr_up[] = 'custom_status';
			$arr_up[] = 'backup_options';
			$args->module_srl = - 1;
		}
		$module_mid = $args->module_mid;
		$target_module_srl = $args->target_module_srl;
		$args->module = __XEFM_NAME__;
		$args->site_srl = (int)$args->site_srl;
		$module_path = _XE_PATH_ . 'modules/beluxe';
		$tpl_path = sprintf('%s/skins/%s', $module_path, $args->skin);
		if (!$args->skin || !is_dir($tpl_path)) $args->skin = 'default';
		$args->mskin = $args->skin.'/mobile';
		$args->is_skin_fix = $args->skin=='/USE_DEFAULT/'?'N':'Y';
		$args->is_mskin_fix = $args->skin=='/USE_DEFAULT/'?'N':'Y';
		$args->use_mobile = (int)$args->mlayout_srl ? 'Y' : 'N';
		$df_option = array();
		$df_option[] = $args->default_sort_index ? $args->default_sort_index : 'list_order';
		$df_option[] = $args->default_order_type == 'desc' ? 'desc' : 'asc';
		$df_option[] = (int)$args->default_list_count;
		$df_option[] = (int)$args->default_page_count;
		$df_option[] = $comment_count = (int)$args->default_clist_count;
		$df_option[] = (int)$args->default_dlist_count;
		$args->default_type_option = implode('|@|', $df_option);
		if (!$args->custom_status) $args->custom_status = '';
		if ($args->tmp_lock_document != 'Y') $args->use_lock_document = 'N';
		if ($args->tmp_lock_comment != 'Y') $args->use_lock_comment = 'N';
		if ($args->tmp_restrict_view != 'Y') $args->use_restrict_view = 'N';
		if ($args->tmp_restrict_down != 'Y' || !file_exists(__XEFM_PATH__ . 'schemas/file_downloaded_log.xml')) $args->use_restrict_down = 'N';
		unset($args->_filter);
		unset($args->ruleset);
		unset($args->is_poped);
		unset($args->error_return_url);
		unset($args->extra_fields);
		unset($args->backup_options);
		unset($args->module_mid);
		unset($args->target_module_srl);
		unset($args->tmp_restrict_view);
		unset($args->tmp_restrict_down);
		unset($args->tmp_lock_document);
		unset($args->tmp_lock_comment);
		unset($args->default_sort_index);
		unset($args->default_order_type);
		unset($args->default_list_count);
		unset($args->default_page_count);
		unset($args->default_clist_count);
		unset($args->default_dlist_count);
		$filename = sprintf('%sskins/%s/type.xml', $this->module_path, $args->skin);
		$parser = XmlParser::loadXmlFile($filename);
		if (count($parser->type->exfields->exfield)) {
			$exfields = is_array($parser->type->exfields->exfield) ? $parser->type->exfields->exfield : array($parser->type->exfields->exfield);
			foreach ($exfields as $val) {
				$name = $val->attrs->name;
				$length = explode(':', trim($val->attrs->length ? $val->attrs->length : '0:0'));
				$ex_fields->{$name}['required'] = strtolower($val->attrs->required) == 'true' ? true : false;
				$ex_fields->{$name}['readonly'] = strtolower($val->attrs->readonly) == 'true' ? true : false;
				$ex_fields->{$name}['default'] = strlen(trim($val->attrs->default)) ? trim($val->attrs->default) : null;
				$ex_fields->{$name}['rule'] = trim($val->attrs->rule ? $val->attrs->rule : '');
				$ex_fields->{$name}['minlength'] = (int)$length[0];
				$ex_fields->{$name}['maxlength'] = (int)$length[1];
			}
			if (count($ex_fields) > 20) return new Object(-1, 'msg_max_extra_fields');
		}
		if (count($parser->type->options->option)) {
			$options = is_array($parser->type->options->option) ? $parser->type->options->option : array($parser->type->options->option);
			$except = array('module', 'mid', 'browser_title', 'site_srl', 'skin', 'layout_srl', 'mlayout_srl', 'admin_mail', 'description', 'header_text', 'footer_text');
			foreach ($options as $val) {
				$name = $val->attrs->name;
				if (!strlen($name) || !strlen($val->body) || in_array($name, $except)) continue;
				if (!isset($args->{$name}) && !in_array($name, $arr_up)) continue;
				$bk_opts->{$name} = true;
				$args->{$name} = $val->body;
			}
		}
		$args = __beluxe_checkArgs($args);
		if($args->use_point_type == 'A') {
			$args->use_restrict_view = 'N';
			$args->use_restrict_down = 'N';
		}
		if (count($bk_opts)) $args->backup_options = serialize($bk_opts);
		if (count($ex_fields)) $args->extra_fields = serialize($ex_fields);
		$t_cfgs = array('document' => array(), 'comment' => array(), 'trackback' => array());
		$t_cfgs['document']['use_history'] = $args->use_history;
		$t_cfgs['comment']['comment_count'] = $comment_count ? $comment_count : '50';
		$t_cfgs['trackback']['enable_trackback'] = $args->allow_trackback == 'N' ? 'N' : 'Y';
		$ccModule = &getController('module');
		$cmModule = &getModel('module');
		$oDB = & DB::getInstance();
		if ($oDB) {
			$oDB->begin();
			if (!$args->module_srl) {
				$args->mid = substr($module_mid, 0, 40);
				if (!$args->browser_title) $args->browser_title = $args->mid;
				$out = $ccModule->insertModule($args);
				if (!$out->toBool()) {
					$oDB->rollback();
					return $out;
				}
				$mod_srl = $out->get('module_srl');
				$msg_code = 'success_registed';
				if ($mod_srl && $target_module_srl) {
					$skin_vars = $cmModule->getModuleSkinVars($target_module_srl);
					unset($skin_obj);
					if (count($skin_vars)) {
						foreach ($skin_vars as $vars) $skin_obj->{$vars->name} = $vars->value;
					}
					$ccModule->insertModuleSkinVars($mod_srl, $skin_obj);
					$ccModule->insertModuleMobileSkinVars($mod_srl, $skin_obj);
				}
				$this->_setModulePartConfig($mod_srl, $t_cfgs);
			} else {
				foreach ($mid_list as $mid) {
					$module_srls = $cmModule->getModuleSrlByMid($mid);
					$args->module_srl = $module_srls[0];
					if (!$args->module_srl) {
						$oDB->rollback();
						return new Object(-1, 'msg_invalid_request');
					}
					$args->mid = substr($mid, 0, 40);
					if (!$args->browser_title) $args->browser_title = $args->mid;
					$oMi = $cmModule->getModuleInfoByModuleSrl($args->module_srl);
					foreach ($arr_up as $vup) $args->{$vup} = $oMi->{$vup};
					$out = $ccModule->updateModule($args);
					if (!$out->toBool()) {
						$oDB->rollback();
						return $out;
					}
					if ($oMi->skin != $args->skin) $_SESSION['BELUXE_MODULE_BACKUP_OPTIONS'] = $oMi->backup_options;
					$mod_srl = $out->get('module_srl');
					$msg_code = 'success_updated';
					$this->_setModulePartConfig($mod_srl, $t_cfgs);
				}
			}
			$oDB->commit();
		} else return new Object(-1, 'msg_dbconnect_failed');
		if (count($mid_list) > 1) {
			$this->add('page', Context::get('page'));
			$this->setMessage($msg_code);
			$this->_setLocation(0, 'dispBeluxeAdminList');
		} else {
			$this->add('page', Context::get('page'));
			$this->add('module_srl', $mod_srl);
			$this->setMessage($msg_code);
			$this->_setLocation($mod_srl, 'dispBeluxeAdminModuleInfo');
		}
	}

	function procBeluxeAdminDelete() {
		$mod_srl = Context::get('module_srl');
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$oDB = & DB::getInstance();
		if ($oDB) {
			$oDB->begin();
			$ccModule = &getController('module');
			$out = $ccModule->deleteModule($mod_srl);
			if (!$out->toBool()) {
				$oDB->rollback();
				return $out;
			}
			$oDB->commit();
		} else return new Object(-1, 'msg_dbconnect_failed');
		$this->add('page', Context::get('page'));
		$this->setMessage('success_deleted');
		$this->_setLocation(0, 'dispBeluxeAdminList');
	}

	function procBeluxeAdminInsertCategory() {
		$mod_srl = Context::get('module_srl');
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$item_key = Context::get('item_key');
		if (is_array($item_key)) {
			$parent_key = Context::get('parent_key');
			$item_title = Context::get('item_title');
			$item_color = Context::get('item_color');
			$group_srls = Context::get('group_srls');
			$item_type = Context::get('item_type');
			$item_opts = Context::get('item_opts');
			$pinf = array();
			$oDB = & DB::getInstance();
			if ($oDB) {
				$oDB->begin();
				foreach ($item_key as $key => $cate_srl) {
					unset($args);
					$args->list_order = ($key + 1) * 100;
					$pinf[$cate_srl] = $cate_srl;
					$args->parent_srl = (int)$pinf[$parent_key[$key]];
					$args->category_srl = ($cate_srl > 0) ? $cate_srl : NULL;
					$args->module_srl = $mod_srl;
					$args->title = trim($item_title[$key]);
					$args->color = trim($item_color[$key]);
					$args->title = $args->title ? $args->title : 'Untitled';
					$args->color = $args->color != 'transparent' ? $args->color : '';
					$args->group_srls = array();
					$groups = explode('|@|', $group_srls[$key]);
					foreach ($groups as $val) {
						if (strlen(trim($val)) < 1) continue;
						$args->group_srls[] = $val;
					}
					$args->group_srls = implode(',', $args->group_srls);
					$opts = explode('|@|', $item_opts[$key]);
					$args->expand = ($opts[0] != 'Y') ? 'N' : 'Y';
					unset($opts[0]);
					$args->description = $item_type[$key] . '|@|' . preg_replace('/[^0-9a-zA-Z_,]/', '', implode(',', $opts)) . '|@|';
					$cmDocument = &getModel('document');
					if ($args->category_srl) {
						$oCateIfo = $cmDocument->getCategory($args->category_srl, array('category_srl'));
						if ($oCateIfo->category_srl != $args->category_srl) $args->category_srl = NULL;
					}
					if ($args->category_srl) {
						$out = $this->doUpdateCategory($args);
					} else {
						$out = $this->doInsertCategory($args);
						if ($out->toBool()) $pinf[$cate_srl] = $out->get('category_srl');
					}
					if (!$out->toBool()) {
						$oDB->rollback();
						return $out;
					}
				}
				$oDB->commit();
			}
			else return new Object(-1, 'msg_dbconnect_failed');
		}
		$default_title = Context::get('default_category_title');
		$this->_setModuleInfo($mod_srl, 'default_category_title', $default_title);
		$ccDocument = &getController('document');
		$ccDocument->makeCategoryFile($args->module_srl);
		$this->_deleteCacheHandler($args->module_srl, array('category_list', 'mobile_category_list'));
		$this->add('module_srl', $args->module_srl);
		$this->add('category_srl', $args->category_srl);
		$this->add('parent_srl', $args->parent_srl);
		$this->setMessage('success_updated');
		$this->_setLocation($args->module_srl, 'dispBeluxeAdminCategoryInfo');
	}

	function procBeluxeAdminDeleteCategory() {
		$args = Context::gets('module_srl', 'category_srl');
		$mod_srl = $args->module_srl;
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$cmDocument = &getModel('document');
		$oCateIfo = $cmDocument->getCategory($args->category_srl, array('parent_srl'));
		$add_cate_srl = ($oCateIfo->parent_srl) ? $oCateIfo->parent_srl : $args->category_srl;
		$out_count = executeQuery('document.getChildCategoryCount', $args);
		if ($out_count->data->count > 0) return new Object(-1, 'msg_cannot_delete_for_child');
		$oDB = & DB::getInstance();
		if ($oDB) {
			$oDB->begin();
			$out = $this->doDeleteCategory($args->category_srl);
			if (!$out->toBool()) {
				$oDB->rollback();
				return $out;
			}
			$oDB->commit();
		} else return new Object(-1, 'msg_dbconnect_failed');
		$ccDocument = &getController('document');
		$ccDocument->makeCategoryFile($mod_srl);
		$this->_deleteCacheHandler($args->module_srl, array('category_list', 'mobile_category_list'));
		$this->add('category_srl', $add_cate_srl);
		$this->setMessage('success_deleted');
	}

	function procBeluxeAdminMakeCategoryCache() {
		$mod_srl = Context::get('module_srl');
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$ccDocument = &getController('document');
		$ccDocument->makeCategoryFile($mod_srl);
		$this->_deleteCacheHandler($args->module_srl, array('category_list', 'mobile_category_list'));
	}

	function procBeluxeAdminColumnSetting() {
		$mod_srl = Context::get('module_srl');
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$column_key = Context::get('column_key');
		$column_option = Context::get('column_option');
		$column_color = Context::get('column_color');
		$list_arr = array();
		foreach ($column_key as $key => $val) {
			$option = explode('|@|', $column_option[$key]);
			$color = trim($column_color[$key]);
			$list_arr[$val] = array($key + 1, ($color != 'transparent' ? $color : ''), $option[0], $option[1], $option[2]);
		}
		$ccModule = &getController('module');
		$out = $ccModule->insertModulePartConfig('beluxe', $mod_srl, $list_arr);
		if (!$out->toBool()) return $out;
		$this->_deleteCacheHandler($mod_srl, array('column_config'));
		$this->setMessage('success_updated');
		$this->_setLocation($mod_srl, 'dispBeluxeAdminColumnInfo');
	}

	function procBeluxeAdminMakeColumnCache() {
		$mod_srl = Context::get('module_srl');
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$this->_deleteCacheHandler($mod_srl, array('column_config'));
	}

	function procBeluxeAdminInsertExtraKey() {
		$mod_srl = Context::get('module_srl');
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$extra_eid = Context::get('extra_eid');
		$extra_idx = Context::get('extra_idx');
		$extra_name = Context::get('extra_name');
		$extra_default = Context::get('extra_default');
		$extra_desc = Context::get('extra_desc');
		$extra_type = Context::get('extra_type');
		$extra_option = Context::get('extra_option');
		if (!is_array($extra_eid)) $extra_eid = array();
		$chk_eid = array();
		$oDB = & DB::getInstance();
		if ($oDB) {
			$oDB->begin();
			foreach ($extra_eid as $key => $val) {
				$val = trim($val);
				if (!$val || preg_match('/^[^a-z]|[^a-z0-9_]+$/i', $val)) continue;
				$eid = $val;
				if ($chk_eid[$eid]) $eid = $eid . '_' . count($chk_eid);
				$var_idx = $key + 1;
				$idx = (int)$extra_idx[$key];
				$type = $extra_type[$key];
				$name = $extra_name[$key];
				$desc = $extra_desc[$key];
				$default = $extra_default[$key];
				$is_required = explode('|@|', $extra_option[$key]);
				$is_required = $is_required[0];
				unset($args);
				$args->module_srl = $mod_srl;
				$args->var_default = $default;
				$args->var_desc = $desc;
				$args->var_name = $name ? $name : $eid;
				$args->var_type = $type ? $type : 'text';
				$args->var_is_required = $is_required == 'Y' ? 'Y' : 'N';
				$args->var_search = 'N';
				$ch_args->module_srl = $mod_srl;
				$args->var_idx = $var_idx;
				$oExtraKeys = executeQuery('document.getDocumentExtraKeys', $args);
				if ($oExtraKeys->data) {
					$ch_args->var_idx = $var_idx;
					$ch_args->new_idx = $new_idx = (time() + $key) * -1;
					$out = executeQuery('document.updateDocumentExtraKeyIdx', $ch_args);
					if ($out->toBool()) $out = executeQuery('document.updateDocumentExtraVarIdx', $ch_args);
					if (!$out->toBool()) {
						$oDB->rollback();
						return $out;
					}
				}
				$args->eid = $eid;
				$oExtraKeys = executeQuery('beluxe.getExtraKeys', $args);
				if (!$oExtraKeys->data) {
					$out = executeQuery('document.insertDocumentExtraKey', $args);
				} else {
					$args->var_idx = $var_idx;
					$out = executeQuery('beluxe.updateExtraKeys', $args);
					if ($out->toBool()) {
						$ch_args->var_idx = $oExtraKeys->data->idx;
						$ch_args->new_idx = $var_idx;
						$out = executeQuery('document.updateDocumentExtraVarIdx', $ch_args);
					}
				}
				if (!$out->toBool()) {
					$oDB->rollback();
					return $out;
				}
				$chk_eid[$eid] = TRUE;
			}
			$oDB->commit();
		} else return new Object(-1, 'msg_dbconnect_failed');
		$this->_deleteCacheHandler($mod_srl, array('document_extra_keys','column_config'));
		$this->setMessage('success_updated');
		$this->_setLocation($mod_srl, 'dispBeluxeAdminExtraKeys');
	}

	function procBeluxeAdminDeleteExtraKey() {
		$args = Context::gets('module_srl', 'extra_idx');
		$mod_srl = $args->module_srl;
		$var_idx = $args->extra_idx;
		if (!$mod_srl || !$var_idx) return new Object(-1, 'msg_invalid_request');
		$ccDocument = &getController('document');
		$out = $ccDocument->deleteDocumentExtraKeys($mod_srl, $var_idx);
		if (!$out->toBool()) return $out;
		$this->_deleteCacheHandler($mod_srl, array('document_extra_keys','column_config'));
		$this->setMessage('success_deleted');
		$this->_setLocation($mod_srl, 'dispBeluxeAdminExtraKeys');
	}

	function procBeluxeAdminUpdateSkinInfo() {
		$mod_srl = Context::get('module_srl');
		$mode = Context::get('_mode');
		if (!$mod_srl) return new Object(-1, 'msg_invalid_request');
		$msync = Context::get('_SET_SYNC_OPTIONS_');
		Context::set('_SET_SYNC_OPTIONS_', '');
		$ccAdmModule = &getAdminController('module');
		$out = $ccAdmModule->procModuleAdminUpdateSkinInfo();
		if ($out && !$out->toBool()) return $out;
		if($mode != 'M') {
			$cmModule = getModel('module');
			$ms_vars = $cmModule->getModuleMobileSkinVars($mod_srl);
			$obj = new stdClass();
			foreach ($ms_vars as $val) $obj->{$val->name} = $val->value;
			foreach ($msync as $key) {
				$val = Context::get($key);
				if (is_object($val)) continue;
				if (is_array($val)) $val = serialize($val);
				$obj->{$key} = $val;
			}
			$ccModule = getController('module');
			$ccModule->deleteModuleMobileSkinVars($mod_srl);
			$args = new stdClass();
			$args->module_srl = $mod_srl;
			foreach ($obj as $key=>$val) {
				$args->name = trim($key);
				$args->value = trim($val);
				$output = executeQuery('module.insertModuleMobileSkinVars', $args);
			}
		}
		$this->setMessage('success_updated');
		$this->_setLocation($mod_srl, 'dispBeluxeAdmin' . ($mode == 'M' ? 'Mobile' : '') . 'SkinInfo');
	}
}
