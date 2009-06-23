<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');
	require_once(TOOLKIT . '/class.xsltprocess.php');
	
	class contentBlueprintsPages extends AdministrationPage {
		protected $_errors;
		protected $_hilights;
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Pages'))));
			
			$this->appendSubheading(__('Pages'), Widget::Anchor(
				__('Create New'), $this->_Parent->getCurrentPageURL() . 'new/',
				__('Create a new page'), 'create button'
			));
			
			$pages = $this->_Parent->Database->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				ORDER BY
					p.sortorder ASC
			");
			
			$aTableHead = array(
				array(__('Title'), 'col'),
				array(__('Template'), 'col'),
				array(__('<acronym title="Universal Resource Locator">URL</acronym>'), 'col'),
				array(__('<acronym title="Universal Resource Locator">URL</acronym> Parameters'), 'col'),
				array(__('Type'), 'col')
			);	
			
			$aTableBody = array();
			
			if (!is_array($pages) or empty($pages)) {
				$aTableBody = array(Widget::TableRow(array(
					Widget::TableData(__('None found.'), 'inactive', null, count($aTableHead))
				), 'odd'));
				
			} else {
				$bOdd = true;
				
				foreach ($pages as $page) {
					$class = array();
					$page_title = $this->_Parent->resolvePageTitle($page['id']);
					$page_url = URL . '/' . $this->_Parent->resolvePagePath($page['id']) . '/';
					$page_edit_url = $this->_Parent->getCurrentPageURL() . 'edit/' . $page['id'] . '/';
					$page_template = $this->__createHandle($page['path'], $page['handle']);
					$page_template_url = $this->_Parent->getCurrentPageURL() . 'template/' . $page_template . '/';
					$page_types = $this->_Parent->Database->fetchCol('type', "
						SELECT
							t.type
						FROM
							`tbl_pages_types` AS t
						WHERE
							t.page_id = '".$page['id']."'
						ORDER BY
							ttype ASC
					");
					
					$col_title = Widget::TableData(Widget::Anchor(
						$page_title, $page_edit_url, $page['handle']
					));
					$col_title->appendChild(Widget::Input("items[{$page['id']}]", null, 'checkbox'));
					
					$col_template = Widget::TableData(Widget::Anchor(
						$page_template . '.xsl',
						$page_template_url
					));
					
					$col_url = Widget::TableData(Widget::Anchor($page_url, $page_url));
					
					if ($page['params']) {
						$col_params = Widget::TableData(trim($page['params'], '/'));
						
					} else {
						$col_params = Widget::TableData(__('None'), 'inactive');
					}
					
					if (!empty($page_types)) {
						$col_types = Widget::TableData(implode(', ', $page_types));
						
					} else {
						$col_types = Widget::TableData(__('None'), 'inactive');
					}
					
					if ($bOdd) $class[] = 'odd';
					if (in_array($page['id'], $this->_hilights)) $class[] = 'failed';
					
					$aTableBody[] = Widget::TableRow(
						array($col_title, $col_template, $col_url, $col_params, $col_types),
						implode(' ', $class)
					);
					
					$bOdd = !$bOdd;
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($aTableHead), null, 
				Widget::TableBody($aTableBody), 'orderable'
			);
			
			$this->Form->appendChild($table);
			
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete'))							
			);
			
			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($tableActions);
		}
		
		public function __viewTemplate() {
			$this->setPageType('form');
			$this->Form->setAttribute('action', URL . '/symphony/blueprints/pages/template/' . $this->_context[1] . '/');
			
			$filename = $this->_context[1] . '.xsl';
			$file_abs = PAGES . '/' . $filename;
			
			if (!@is_file($file_abs)) redirect(URL . '/symphony/blueprints/pages/');
			
			$fields['body'] = @file_get_contents($file_abs);
			
			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));			
			if($formHasErrors) $this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR);
			
			// Status message:
			if (isset($this->_context[2])) {
				$this->pageAlert(
					__(
						'%s %s at %s. <a href="%s">View all %s</a>',
						array(
							__('Page'), 'updated',
							DateTimeObj::get(__SYM_TIME_FORMAT__),
							URL . '/symphony/blueprints/pages/',
							__('Pages')
						)
					),
					Alert::SUCCESS
				);
			}
			
			$this->setTitle(__(
				($filename ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Pages'),
					$filename
				)
			));
			$this->appendSubheading(($filename ? $filename : __('Untitled')));
			
			if (!empty($_POST)) $fields = $_POST['fields'];
			
			$fields['body'] = General::sanitize($fields['body']);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'primary');
			
			$label = Widget::Label(__('Body'));
			$label->appendChild(Widget::Textarea(
				'fields[body]', 30, 80, $fields['body'],
				array(
					'class'	=> 'code'
				)
			));
			
			if (isset($this->_errors['body'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['body']);
			}
			
			$fieldset->appendChild($label);
			$this->Form->appendChild($fieldset);
			
			$utilities = General::listStructure(UTILITIES, array('xsl'), false, 'asc', UTILITIES);
			$utilities = $utilities['filelist'];
			
			if (is_array($utilities) && !empty($utilities)) {
				$div = new XMLElement('div');
				$div->setAttribute('class', 'secondary');
				
				$h3 = new XMLElement('h3', __('Utilities'));
				$h3->setAttribute('class', 'label');
				$div->appendChild($h3);
				
				$ul = new XMLElement('ul');
				$ul->setAttribute('id', 'utilities');
				
				foreach ($utilities as $index => $util) {
					$li = new XMLElement('li');
					
					if ($index % 2 != 1) $li->setAttribute('class', 'odd');
					
					$li->appendChild(Widget::Anchor($util, URL . '/symphony/blueprints/utilities/edit/' . str_replace('.xsl', '', $util) . '/', NULL));
					$ul->appendChild($li);
				}
				
				$div->appendChild($ul);
				$this->Form->appendChild($div);
			}
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input(
				'action[save]', __('Save Changes'),
				'submit', array('accesskey' => 's')
			));
			
			$this->Form->appendChild($div);
		}
		
		public function __actionTemplate() {
			$filename = $this->_context[1] . '.xsl';
			$file_abs = PAGES . '/' . $filename;
			$fields = $_POST['fields'];
			$this->_errors = array();
			
			if (!isset($fields['body']) || trim($fields['body']) == '') {
				$this->_errors['body'] = __('Body is a required field.');
				
			} else if (!General::validateXML($fields['body'], $errors, false, new XSLTProcess())) {
				$this->_errors['body'] = __('This document is not well formed. The following error was returned: <code>%s</code>', array($errors[0]['message']));
			}
			
			if (empty($this->_errors)) {
				if (!$write = General::writeFile($file_abs, $fields['body'], $this->_Parent->Configuration->get('write_mode', 'file'))) {
					$this->pageAlert(__('Utility could not be written to disk. Please check permissions on <code>/workspace/utilities</code>.'), Alert::ERROR);
					
				} else {
					redirect(URL . '/symphony/blueprints/pages/template/' . $this->_context[1] . '/saved/');
				}
			}
		}
		
		public function __viewNew() {
			$this->__viewEdit();
		}
		
		public function __viewEdit() {
			$this->setPageType('form');
			$fields = array();
			
			// Verify page exists:
			if ($this->_context[0] == 'edit') {
				if (!$page_id = $this->_context[1]) redirect(URL . '/symphony/blueprints/pages/');
				
				$existing = $this->_Parent->Database->fetchRow(0, "
					SELECT
						p.*
					FROM
						`tbl_pages` AS p
					WHERE
						p.id = '{$page_id}'
					LIMIT 1
				");
				
				if (!$existing) {
					$this->_Parent->customError(
						E_USER_ERROR, __('Page not found'),
						__('The page you requested to edit does not exist.'),
						false, true, 'error', array(
							'header'	=> 'HTTP/1.0 404 Not Found'
						)
					);
				}
			}
			
			// Status message:
			if (isset($this->_context[2])) {
				$this->pageAlert(
					__(
						'%s %s at %s. <a href="%s">Create another?</a> <a href="%s">View all %s</a>',
						array(
							__('Page'),
							($this->_context[2] == 'saved' ? 'updated' : 'created'),
							DateTimeObj::get(__SYM_TIME_FORMAT__),
							URL . '/symphony/blueprints/pages/new/',
							URL . '/symphony/blueprints/pages/',
							__('Pages')
						)
					),
					Alert::SUCCESS
				);
			}
			
			// Find values:
			if (isset($_POST['fields'])) {
				$fields = $_POST['fields'];
				
			} else if ($this->_context[0] == 'edit') {
				$fields = $existing;
				$types = $this->_Parent->Database->fetchCol('type', "
					SELECT
						p.type
					FROM
						`tbl_pages_types` AS p
					WHERE
						p.page_id = '{$page_id}'
					ORDER BY
						p.type ASC
				");
				
				$fields['type'] = @implode(', ', $types);
				$fields['data_sources'] = preg_split('/,/i', $fields['data_sources'], -1, PREG_SPLIT_NO_EMPTY);
				$fields['events'] = preg_split('/,/i', $fields['events'], -1, PREG_SPLIT_NO_EMPTY);
			}
			
			$title = $fields['title'];
			if (trim($title) == '') $title = $existing['title'];
			
			$this->setTitle(__(
				($title ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Pages'),
					$title
				)
			));
			$this->appendSubheading(($title ? $title : __('Untitled')));
			
		// Title --------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Page Settings')));
			
			$label = Widget::Label(__('Title'));		
			$label->appendChild(Widget::Input(
				'fields[title]', General::sanitize($fields['title'])
			));
			
			if (isset($this->_errors['title'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['title']);
			}
			
			$fieldset->appendChild($label);
			
		// Handle -------------------------------------------------------------
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$column = new XMLElement('div');
			
			$label = Widget::Label(__('URL Handle'));
			$label->appendChild(Widget::Input(
				'fields[handle]', $fields['handle']
			));
			
			if (isset($this->_errors['handle'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['handle']);
			}
			
			$column->appendChild($label);
			
		// Parent ---------------------------------------------------------
			
			$label = Widget::Label(__('Parent Page'));
			
			$pages = $this->_Parent->Database->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				WHERE
					p.id != '{$page_id}'
				ORDER BY
					p.title ASC
			");
			
			$options = array(
				array('', false, '/')
			);
			
			if (is_array($pages) && !empty($pages)) {
				if (!function_exists('__compare_pages')) {
					function __compare_pages($a, $b) {
						return strnatcasecmp($a[2], $b[2]);
					}
				}
				
				foreach ($pages as $page) {
					$options[] = array(
						$page['id'], $fields['parent'] == $page['id'],
						'/' . $this->_Parent->resolvePagePath($page['id'])
					);
				}
				
				usort($options, '__compare_pages');
			}
			
			$label->appendChild(Widget::Select(
				'fields[parent]', $options
			));
			$column->appendChild($label);
			$group->appendChild($column);
			
		// Parameters ---------------------------------------------------------
			
			$column = new XMLElement('div');
			$label = Widget::Label(__('URL Parameters'));
			$label->appendChild(Widget::Input(
				'fields[params]', $fields['params']
			));				
			$column->appendChild($label);
			
		// Type -----------------------------------------------------------
			
			$label = Widget::Label(__('Page Type'));
			$label->appendChild(Widget::Input('fields[type]', $fields['type']));
			
			if (isset($this->_errors['type'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['type']);
			}
			
			$column->appendChild($label);
			
			$tags = new XMLElement('ul');
			$tags->setAttribute('class', 'tags');
			
			if ($types = $this->__fetchAvailablePageTypes()) {
				foreach($types as $type) $tags->appendChild(new XMLElement('li', $type));
			}
			$column->appendChild($tags);
			$group->appendChild($column);
			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);
			
		// Events -------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Page Resources')));
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Events'));
			
			$manager = new EventManager($this->_Parent);
			$events = $manager->listAll();
			
			$options = array();
			
			if (is_array($events) && !empty($events)) {		
				foreach ($events as $name => $about) $options[] = array(
					$name, @in_array($name, $fields['events']), $about['name']
				);
			}

			$label->appendChild(Widget::Select('fields[events][]', $options, array('multiple' => 'multiple')));		
			$group->appendChild($label);
			
		// Data Sources -------------------------------------------------------

			$label = Widget::Label(__('Data Sources'));
			
			$manager = new DatasourceManager($this->_Parent);
			$datasources = $manager->listAll();
			
			$options = array();
			
			if (is_array($datasources) && !empty($datasources)) {		
				foreach ($datasources as $name => $about) $options[] = array(
					$name, @in_array($name, $fields['data_sources']), $about['name']
				);
			}
			
			$label->appendChild(Widget::Select('fields[data_sources][]', $options, array('multiple' => 'multiple')));
			$group->appendChild($label);
			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);
			
		// Controls -----------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input(
				'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create Page')),
				'submit', array('accesskey' => 's')
			));
			
			if($this->_context[0] == 'edit'){
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this page')));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
		}
		
		protected function __typeUsed($page_id, $type) {
			$row = $this->_Parent->Database->fetchRow(0, "
				SELECT
					p.*
				FROM
					`tbl_pages_types` AS p
				WHERE
					p.page_id != '{$page_id}'
					AND p.type = '{$type}'
				LIMIT 1
			");
			
			return ($row ? true : false);
		}
		
		public function __actionNew() {
			$this->__actionEdit();
		}
		
		public function __actionEdit() {
			if ($this->_context[0] != 'new' && !$page_id = (integer)$this->_context[1]) {
				redirect(URL . '/symphony/blueprints/pages/');
			}
			
			if (@array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete($page_id);
			}
			
			if (@array_key_exists('save', $_POST['action'])) {
				$fields = $_POST['fields'];
				$this->_errors = array();
				
				$current = $this->_Parent->Database->fetchRow(0, "
					SELECT
						p.*
					FROM
						`tbl_pages` AS p
					WHERE
						p.id = '{$page_id}'
					LIMIT 1
				");
				
				if (!isset($fields['title']) || trim($fields['title']) == '') {
					$this->_errors['title'] = __('Title is a required field');
				}
				
				if (trim($fields['type']) != '' && preg_match('/(index|404|403)/i', $fields['type'])) {
					$haystack = strtolower($fields['type']);
					
					if (preg_match('/\bindex\b/i', $haystack, $matches) && $this->__typeUsed($page_id, 'index')) {
						$this->_errors['type'] = __('An index type page already exists.');
						
					} else if(preg_match('/\b404\b/i', $haystack, $matches) && $this->__typeUsed($page_id, 'index')) {	
						$this->_errors['type'] = __('A 404 type page already exists.');
						
					} else if(preg_match('/\b403\b/i', $haystack, $matches) && $this->__typeUsed($page_id, 'index')) {	
						$this->_errors['type'] = __('A 403 type page already exists.');
					}
				}
				
				if (empty($this->_errors)) {
					$autogenerated_handle = false;
					
					if (empty($current)) {
						$fields['sortorder'] = $this->_Parent->Database->fetchVar('next', 0, "
							SELECT
								MAX(p.sortorder) + 1 AS `next`
							FROM
								`tbl_pages` AS p
							LIMIT 1
						");
						
						if (empty($fields['sortorder']) || !is_numeric($fields['sortorder'])) {
							$fields['sortorder'] = 1;
						}
					}
					
					if (trim($fields['handle'] ) == '') { 
						$fields['handle'] = $fields['title'];
						$autogenerated_handle = true;
					}
					
					$fields['handle'] = Lang::createHandle($fields['handle']);		

					if ($fields['params']) {
						$fields['params'] = trim(preg_replace('@\/{2,}@', '/', $fields['params']), '/');
					}
					
					// Clean up type list
					$types = preg_split('/\s*,\s*/', $fields['type'], -1, PREG_SPLIT_NO_EMPTY);
					$types = @array_map('trim', $types);
					unset($fields['type']);
					
					$fields['parent'] = ($fields['parent'] != __('None') ? $fields['parent'] : null);
					$fields['data_sources'] = @implode(',', $fields['data_sources']);			
					$fields['events'] = @implode(',', $fields['events']);
					$fields['path'] = null;
					
					if ($fields['parent']) {
						$fields['path'] = $this->_Parent->resolvePagePath((integer)$fields['parent']);
					}
					
					// Check for duplicates:
					$duplicate = $this->_Parent->Database->fetchRow(0, "
						SELECT
							p.*
						FROM
							`tbl_pages` AS p
						WHERE
							p.id != '{$page_id}'
							AND p.handle = '" . $fields['handle'] . "'
							AND p.path " . ($fields['path'] ? " = '" . $fields['path'] . "'" : ' IS NULL') .  " 
						LIMIT 1
					");
					
					// Create or move files:
					if (empty($current)) {
						$file_created = $this->__updatePageFiles(
							$fields['path'], $fields['handle']
						);
						
					} else {
						$file_created = $this->__updatePageFiles(
							$fields['path'], $fields['handle'],
							$current['path'], $current['handle']
						);
					}
					
					if (!$file_created) {
						$redirect = null;
						$this->pageAlert(
							__('Page could not be written to disk. Please check permissions on <code>/workspace/pages</code>.'),
							Alert::ERROR
						);
					}
					
					if ($duplicate) {
						if ($autogenerated_handle) {
							$this->_errors['title'] = __('A page with that title already exists');
							
						} else {
							$this->_errors['handle'] = __('A page with that handle already exists'); 
						}
						
					// Insert the new data:
					} else if (empty($current)) {
						if (!$this->_Parent->Database->insert($fields, 'tbl_pages')) {
							$this->pageAlert(
								__(
									'Unknown errors occurred while attempting to save. Please check your <a href="%s">activity log</a>.',
									array(
										URL . '/symphony/system/log/'
									)
								),
								Alert::ERROR
							);
							
						} else {
							$page_id = $this->_Parent->Database->getInsertID();
							$redirect = "/symphony/blueprints/pages/edit/{$page_id}/created/";
						}
						
					// Update existing:
					} else {
						if (!$this->_Parent->Database->update($fields, 'tbl_pages', "`id` = '$page_id'")) {
							$this->pageAlert(
								__(
									'Unknown errors occurred while attempting to save. Please check your <a href="%s">activity log</a>.',
									array(
										URL . '/symphony/system/log/'
									)
								),
								Alert::ERROR
							);
							
						} else {
							$this->_Parent->Database->delete('tbl_pages_types', " `page_id` = '$page_id'");
							$redirect = "/symphony/blueprints/pages/edit/{$page_id}/saved/";
						}
					}
					
					// Assign page types:
					if (is_array($types) && !empty($types)) {
						foreach ($types as $type) $this->_Parent->Database->insert(
							array(
								'page_id' => $page_id,
								'type' => $type
							),
							'tbl_pages_types'
						);
					}
					
					// Find and update children:
					if ($this->_context[0] == 'edit') {
						$this->__updatePageChildren($page_id, $fields['path'] . '/' . $fields['handle']);
					}
					
					if ($redirect) redirect(URL . $redirect);
				}
				
				if (is_array($this->_errors) && !empty($this->_errors)) {
					$this->pageAlert(
						__('An error occurred while processing this form. <a href="#error">See below for details.</a>'),
						Alert::ERROR
					);
				}
			}			
		}
		
		protected function __updatePageChildren($page_id, $page_path, &$success = true) {
			$page_path = trim($page_path, '/');
			$children = $this->_Parent->Database->fetch("
				SELECT
					p.id, p.path, p.handle
				FROM
					`tbl_pages` AS p
				WHERE
					p.id != '{$page_id}'
					AND p.parent = '{$page_id}'
			");
			
			foreach ($children as $child) {
				$child_id = $child['id'];
				$fields = array(
					'path'	=> $page_path
				);
				
				if (!$this->__updatePageFiles($page_path, $child['handle'], $child['path'], $child['handle'])) {
					$success = false;
				}
				
				if (!$this->_Parent->Database->update($fields, 'tbl_pages', "`id` = '$child_id'")) {
					$success = false;
				}
				
				$this->__updatePageChildren($child_id, $page_path . '/' . $child['handle']);
			}
			
			return $success;
		}
		
		protected function __createHandle($path, $handle) {
			return trim(str_replace('/', '_', $path . '_' . $handle), '_');
		}
		
		protected function __updatePageFiles($new_path, $new_handle, $old_path = null, $old_handle = null) {
			$new = PAGES . '/' . $this->__createHandle($new_path, $new_handle) . '.xsl';
			$old = PAGES . '/' . $this->__createHandle($old_path, $old_handle) . '.xsl';
			$data = null;
			
			// Nothing to do:
			if (file_exists($new) && $new == old) return true;
			
			// Old file doesn't exist, use template:
			if (!file_exists($old)) {
				$data = file_get_contents(TEMPLATE . '/page.xsl');
				
			} else {
				$data = file_get_contents($old); @unlink($old);
			}
			
			return General::writeFile($new, $data, $this->_Parent->Configuration->get('write_mode', 'file'));
		}
		
		protected function __deletePageFiles($path, $handle) {
			$file = PAGES . '/' . trim(str_replace('/', '_', $path . '_' . $handle), '_') . '.xsl';
			
			// Nothing to do:
			if (!file_exists($file)) return true;
			
			// Delete it:
			if (@unlink($file)) return true;
			
			return false;
		}
		
		protected function __hasChildren($page_id) {
			return (boolean)$this->_Parent->Database->fetchVar('id', 0, "
				SELECT
					p.id
				FROM
					`tbl_pages` AS p
				WHERE
					p.parent = '{$page_id}'
				LIMIT 1
			");
		}
		
		protected function __actionDelete($pages, $redirect) {
			$success = true;
			
			if (!is_array($pages)) $pages = array($pages);
			
			foreach ($pages as $page_id) {
				$page = $this->_Parent->Database->fetchRow(0, "
					SELECT
						p.*
					FROM
						`tbl_pages` AS p
					WHERE
						p.id = '{$page_id}'
					LIMIT 1
				");
				
				if (empty($page)) {
					$success = false;
					$this->pageAlert(
						__('Page could not be deleted because it does not exist.'),
						Alert::ERROR
					);
					
					break;
				}
				
				if ($this->__hasChildren($page_id)) {
					$this->_hilights[] = $page['id'];
					$success = false;
					$this->pageAlert(
						__('Page could not be deleted because it has children.'),
						Alert::ERROR
					);
					
					continue;
				}
				
				if (!$this->__deletePageFiles($page['path'], $page['handle'])) {
					$this->_hilights[] = $page['id'];
					$success = false;
					$this->pageAlert(
						__('One or more pages could not be deleted. Please check permissions on <code>/workspace/pages</code>.'),
						Alert::ERROR
					);
					
					continue;
				}
				
				$this->_Parent->Database->delete('tbl_pages', " `id` = '{$page_id}'");
				$this->_Parent->Database->delete('tbl_pages_types', " `page_id` = '{$page_id}'");
				$this->_Parent->Database->query("
					UPDATE
						tbl_pages
					SET
						`sortorder` = (`sortorder` + 1)
					WHERE
						`sortorder` < '$page_id'
				");
			}
			
			if ($success) redirect($redirect);
		}
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__actionDelete($checked, URL . '/symphony/blueprints/pages/');
						break; 
				}
			}
		}	
	}
	
?>