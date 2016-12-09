<?php
require_once(_XE_PATH_.'modules/document/document.item.php');

class document extends ModuleObject {
	var $tmp_dir = './files/cache/tmp';
	var $search_option = array('title','content','title_content','user_name',);
	var $statusList = array('private'=>'PRIVATE', 'public'=>'PUBLIC', 'secret'=>'SECRET', 'temp'=>'TEMP');
	function moduleInstall() {
		$oModuleController = getController('module');
		$oDB = &DB::getInstance();
		$oDB->addIndex("documents","idx_module_list_order", array("module_srl","list_order"));
		$oDB->addIndex("documents","idx_module_update_order", array("module_srl","update_order"));
		$oDB->addIndex("documents","idx_module_readed_count", array("module_srl","readed_count"));
		$oDB->addIndex("documents","idx_module_voted_count", array("module_srl","voted_count"));
		$oDB->addIndex("documents","idx_module_notice", array("module_srl","is_notice"));
		$oDB->addIndex("documents","idx_module_document_srl", array("module_srl","document_srl"));
		$oDB->addIndex("documents","idx_module_blamed_count", array("module_srl","blamed_count"));
		$oDB->addIndex("document_aliases", "idx_module_title", array("module_srl","alias_title"), true);
		$oDB->addIndex("document_extra_vars", "unique_extra_vars", array("module_srl","document_srl","var_idx","lang_code"), true);
		$oModuleController->insertTrigger('module.deleteModule', 'document', 'controller', 'triggerDeleteModuleDocuments', 'after');
		$oModuleController->insertTrigger('module.dispAdditionSetup', 'document', 'view', 'triggerDispDocumentAdditionSetup', 'before');
		if(!is_dir($this->tmp_dir)) FileHandler::makeDir($this->tmp_dir);
		return new Object();
	}

	function checkUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		if(!$oDB->isColumnExists("documents","notify_message")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_list_order")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_update_order")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_readed_count")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_voted_count")) return true;
		if(!$oModuleModel->getTrigger('module.deleteModule', 'document', 'controller', 'triggerDeleteModuleDocuments', 'after')) return true;
		if(!$oDB->isColumnExists("document_categories","parent_srl")) return true;
		if(!$oDB->isColumnExists("document_categories","expand")) return true;
		if(!$oDB->isColumnExists("document_categories","group_srls")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_notice")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_document_srl")) return true;
		if(!$oDB->isColumnExists("documents","extra_vars")) return true;
		if(!$oDB->isColumnExists("documents", "blamed_count")) return true;
		if(!$oDB->isIndexExists("documents","idx_module_blamed_count")) return true;
		if(!$oDB->isColumnExists("document_voted_log", "point")) return true;
		if(!$oDB->isColumnExists("document_categories", "color")) return true;
		if(!$oDB->isColumnExists("document_extra_vars","lang_code")) return true;
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'document', 'view', 'triggerDispDocumentAdditionSetup', 'before')) return true;
		if(!$oDB->isColumnExists("documents","lang_code")) return true;
		if(!$oDB->isIndexExists("document_extra_vars", "unique_extra_vars")) return true;
		if(!$oDB->isColumnExists("document_extra_keys","eid")) return true;
		if(!$oDB->isColumnExists("document_extra_vars","eid")) return true;
		if(!$oDB->isIndexExists("document_extra_vars", "idx_document_list_order")) return true;
		if(!$oDB->isColumnExists("document_categories","description")) return true;
		if(!$oDB->isColumnExists('documents', 'status')) return true;
		if($oDB->isColumnExists('documents', 'allow_comment') || $oDB->isColumnExists('documents', 'lock_comment')) return true;
		if(!$oDB->isIndexExists("documents", "idx_module_status")) return true;
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'document', 'controller', 'triggerCopyModuleExtraKeys', 'after')) return true;
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'document', 'controller', 'triggerCopyModule', 'after')) return true;
		return false;
	}

	function moduleUpdate() {
		$oDB = &DB::getInstance();
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		if(!$oDB->isColumnExists("documents","notify_message")) $oDB->addColumn('documents',"notify_message","char",1);
		if(!$oDB->isIndexExists("documents","idx_module_list_order")) $oDB->addIndex("documents","idx_module_list_order", array("module_srl","list_order"));
		if(!$oDB->isIndexExists("documents","idx_module_update_order")) $oDB->addIndex("documents","idx_module_update_order", array("module_srl","update_order"));
		if(!$oDB->isIndexExists("documents","idx_module_readed_count")) $oDB->addIndex("documents","idx_module_readed_count", array("module_srl","readed_count"));
		if(!$oDB->isIndexExists("documents","idx_module_voted_count")) $oDB->addIndex("documents","idx_module_voted_count", array("module_srl","voted_count"));
		if(!$oModuleModel->getTrigger('module.deleteModule', 'document', 'controller', 'triggerDeleteModuleDocuments', 'after'))
			$oModuleController->insertTrigger('module.deleteModule', 'document', 'controller', 'triggerDeleteModuleDocuments', 'after');
		if(!$oDB->isColumnExists("document_categories","parent_srl")) $oDB->addColumn('document_categories',"parent_srl","number",12,0);
		if(!$oDB->isColumnExists("document_categories","expand")) $oDB->addColumn('document_categories',"expand","char",1,"N");
		if(!$oDB->isColumnExists("document_categories","group_srls")) $oDB->addColumn('document_categories',"group_srls","text");
		if(!$oDB->isIndexExists("documents","idx_module_notice")) $oDB->addIndex("documents","idx_module_notice", array("module_srl","is_notice"));
		if(!$oDB->isColumnExists("documents","extra_vars")) $oDB->addColumn('documents','extra_vars','text');
		if(!$oDB->isIndexExists("documents","idx_module_document_srl")) $oDB->addIndex("documents","idx_module_document_srl", array("module_srl","document_srl"));
		if(!$oDB->isColumnExists("documents", "blamed_count")) {
			$oDB->addColumn('documents', 'blamed_count', 'number', 11, 0, true);
			$oDB->addIndex('documents', 'idx_blamed_count', array('blamed_count'));
		}
		if(!$oDB->isIndexExists("documents","idx_module_blamed_count")) $oDB->addIndex('documents', 'idx_module_blamed_count', array('module_srl', 'blamed_count'));
		if(!$oDB->isColumnExists("document_voted_log", "point")) $oDB->addColumn('document_voted_log', 'point', 'number', 11, 0, true);
		if(!$oDB->isColumnExists("document_categories","color")) $oDB->addColumn('document_categories',"color","char",7);
		if(!$oDB->isColumnExists("document_extra_vars","lang_code")) $oDB->addColumn('document_extra_vars',"lang_code","varchar",10);
		if(!$oModuleModel->getTrigger('module.dispAdditionSetup', 'document', 'view', 'triggerDispDocumentAdditionSetup', 'before'))
			$oModuleController->insertTrigger('module.dispAdditionSetup', 'document', 'view', 'triggerDispDocumentAdditionSetup', 'before');
		if(!$oDB->isColumnExists("documents","lang_code")) {
			$db_info = Context::getDBInfo();
			$oDB->addColumn('documents',"lang_code","varchar",10, $db_info->lang_code);
			$obj->lang_code = $db_info->lang_type;
			executeQuery('document.updateDocumentsLangCode', $obj);
		}
		if(!$oDB->isIndexExists("document_extra_vars", "unique_extra_vars")) $oDB->addIndex("document_extra_vars", "unique_extra_vars", array("module_srl","document_srl","var_idx","lang_code"), true);
		if($oDB->isIndexExists("document_extra_vars", "unique_module_vars")) $oDB->dropIndex("document_extra_vars", "unique_module_vars", true);
		if(!$oDB->isColumnExists("document_extra_keys","eid")) {
			$oDB->addColumn("document_extra_keys","eid","varchar",40);
			$output = executeQuery('document.getGroupsExtraKeys', $obj);
			if($output->toBool() && $output->data && count($output->data)) {
				foreach($output->data as $extra_keys) {
					$args->module_srl = $extra_keys->module_srl;
					$args->var_idx = $extra_keys->idx;
					$args->new_eid = "extra_vars".$extra_keys->idx;
					$output = executeQuery('document.updateDocumentExtraKeyEid', $args);
				}
			}
		}
		if(!$oDB->isColumnExists("document_extra_vars","eid")) {
			$oDB->addColumn("document_extra_vars","eid","varchar",40);
			$obj->var_idx = '-1,-2';
			$output = executeQuery('document.getGroupsExtraVars', $obj);
			if($output->toBool() && $output->data && count($output->data)) {
				foreach($output->data as $extra_vars) {
					$args->module_srl = $extra_vars->module_srl;
					$args->var_idx = $extra_vars->idx;
					$args->new_eid = "extra_vars".$extra_vars->idx;
					$output = executeQuery('document.updateDocumentExtraVarEid', $args);
				}
			}
		}
		if(!$oDB->isIndexExists("document_extra_vars", "idx_document_list_order")) $oDB->addIndex("document_extra_vars", "idx_document_list_order", array("document_srl","module_srl","var_idx"), false);
		if(!$oDB->isColumnExists("document_categories","description")) $oDB->addColumn('document_categories',"description","varchar",200,0);
		if(!$oDB->isColumnExists('documents', 'status')) {
			$oDB->addColumn('documents', 'status', 'varchar', 20, 'PUBLIC');
			$args->is_secret = 'Y';
			$output = executeQuery('document.updateDocumentStatus', $args);
		}
		if($oDB->isColumnExists('documents', 'status') && $oDB->isColumnExists('documents', 'is_secret')) $oDB->dropColumn('documents', 'is_secret');
		if($oDB->isColumnExists('documents', 'allow_comment') || $oDB->isColumnExists('documents', 'lock_comment')) {
			$oDB->addColumn('documents', 'comment_status', 'varchar', 20, 'ALLOW');
			$args->commentStatus = 'DENY';
			$args->allowComment = 'Y';
			$args->lockComment = 'Y';
			$output = executeQuery('document.updateDocumentCommentStatus', $args);
			$args->allowComment = 'N';
			$args->lockComment = 'Y';
			$output = executeQuery('document.updateDocumentCommentStatus', $args);
			$args->allowComment = 'N';
			$args->lockComment = 'N';
			$output = executeQuery('document.updateDocumentCommentStatus', $args);
		}
		if($oDB->isColumnExists('documents', 'allow_comment') && $oDB->isColumnExists('documents', 'comment_status')) $oDB->dropColumn('documents', 'allow_comment');
		if($oDB->isColumnExists('documents', 'lock_comment') && $oDB->isColumnExists('documents', 'comment_status')) $oDB->dropColumn('documents', 'lock_comment');
		if(!$oDB->isIndexExists("documents", "idx_module_status")) $oDB->addIndex("documents", "idx_module_status", array("module_srl","status"));
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'document', 'controller', 'triggerCopyModuleExtraKeys', 'after'))
			$oModuleController->insertTrigger('module.procModuleAdminCopyModule', 'document', 'controller', 'triggerCopyModuleExtraKeys', 'after');
		if(!$oModuleModel->getTrigger('module.procModuleAdminCopyModule', 'document', 'controller', 'triggerCopyModule', 'after'))
			$oModuleController->insertTrigger('module.procModuleAdminCopyModule', 'document', 'controller', 'triggerCopyModule', 'after');
		return new Object(0,'success_updated');
	}

	function recompileCache() {
		if(!is_dir($this->tmp_dir)) FileHandler::makeDir($this->tmpdir);
	}

	function getStatusList() {
		return $this->statusList;
	}

	function getDefaultStatus() {
		return $this->statusList['public'];
	}

	function getConfigStatus($key) {
		if(array_key_exists(strtolower($key), $this->statusList)) return $this->statusList[$key];
		else $this->getDefaultStatus();
	}
}
