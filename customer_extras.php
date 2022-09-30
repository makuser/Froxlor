<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, you can also view it online at
 * https://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  the authors
 * @author     Froxlor team <team@froxlor.org>
 * @license    https://files.froxlor.org/misc/COPYING.txt GPLv2
 */

const AREA = 'customer';
require __DIR__ . '/lib/init.php';

use Froxlor\Api\Commands\CustomerBackups as CustomerBackups;
use Froxlor\Api\Commands\DirOptions as DirOptions;
use Froxlor\Api\Commands\DirProtections as DirProtections;
use Froxlor\Customer\Customer;
use Froxlor\FileDir;
use Froxlor\FroxlorLogger;
use Froxlor\PhpHelper;
use Froxlor\Settings;
use Froxlor\UI\Collection;
use Froxlor\UI\HTML;
use Froxlor\UI\Listing;
use Froxlor\UI\Panel\UI;
use Froxlor\UI\Request;
use Froxlor\UI\Response;

// redirect if this customer page is hidden via settings
if (Settings::IsInList('panel.customer_hide_options', 'extras')) {
	Response::redirectTo('customer_index.php');
}

$id = (int)Request::get('id');

if ($page == 'overview' || $page == 'htpasswds') {
	// redirect if this customer sub-page is hidden via settings
	if (Settings::IsInList('panel.customer_hide_options', 'extras.directoryprotection')) {
		Response::redirectTo('customer_index.php');
	}

	if ($action == '') {
		$log->logAction(FroxlorLogger::USR_ACTION, LOG_NOTICE, "viewed customer_extras::htpasswds");
		$fields = [
			'username' => lng('login.username'),
			'path' => lng('panel.path')
		];
		try {
			$htpasswd_list_data = include_once dirname(__FILE__) . '/lib/tablelisting/customer/tablelisting.htpasswd.php';
			$collection = (new Collection(DirProtections::class, $userinfo))
				->withPagination($htpasswd_list_data['htpasswd_list']['columns'], $htpasswd_list_data['htpasswd_list']['default_sorting']);
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}

		UI::view('user/table.html.twig', [
			'listing' => Listing::format($collection, $htpasswd_list_data, 'htpasswd_list'),
			'actions_links' => [
				[
					'href' => $linker->getLink(['section' => 'extras', 'page' => 'htpasswds', 'action' => 'add']),
					'label' => lng('extras.directoryprotection_add')
				]
			],
			'entity_info' => lng('extras.description')
		]);
	} elseif ($action == 'delete' && $id != 0) {
		try {
			$json_result = DirProtections::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['username']) && $result['username'] != '') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					DirProtections::getLocal($userinfo, $_POST)->delete();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => $page
				]);
			} else {
				if (strpos($result['path'], $userinfo['documentroot']) === 0) {
					$result['path'] = str_replace($userinfo['documentroot'], "/", $result['path']);
				}

				HTML::askYesNo('extras_reallydelete', $filename, [
					'id' => $id,
					'page' => $page,
					'action' => $action
				], $result['username'] . ' (' . $result['path'] . ')');
			}
		}
	} elseif ($action == 'add') {
		if (isset($_POST['send']) && $_POST['send'] == 'send') {
			try {
				DirProtections::getLocal($userinfo, $_POST)->add();
			} catch (Exception $e) {
				Response::dynamicError($e->getMessage());
			}
			Response::redirectTo($filename, [
				'page' => $page
			]);
		} else {
			$pathSelect = FileDir::makePathfield($userinfo['documentroot'], $userinfo['guid'], $userinfo['guid']);

			$htpasswd_add_data = include_once dirname(__FILE__) . '/lib/formfields/customer/extras/formfield.htpasswd_add.php';

			UI::view('user/form.html.twig', [
				'formaction' => $linker->getLink(['section' => 'extras']),
				'formdata' => $htpasswd_add_data['htpasswd_add']
			]);
		}
	} elseif ($action == 'edit' && $id != 0) {
		try {
			$json_result = DirProtections::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['username']) && $result['username'] != '') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					DirProtections::getLocal($userinfo, $_POST)->update();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => $page
				]);
			} else {
				if (strpos($result['path'], $userinfo['documentroot']) === 0) {
					$result['path'] = str_replace($userinfo['documentroot'], "/", $result['path']);
				}
				$result = PhpHelper::htmlentitiesArray($result);

				$htpasswd_edit_data = include_once dirname(__FILE__) . '/lib/formfields/customer/extras/formfield.htpasswd_edit.php';

				UI::view('user/form.html.twig', [
					'formaction' => $linker->getLink(['section' => 'extras', 'id' => $id]),
					'formdata' => $htpasswd_edit_data['htpasswd_edit'],
					'editid' => $id
				]);
			}
		}
	}
} elseif ($page == 'htaccess') {
	// redirect if this customer sub-page is hidden via settings
	if (Settings::IsInList('panel.customer_hide_options', 'extras.pathoptions')) {
		Response::redirectTo('customer_index.php');
	}

	if ($action == '') {
		$log->logAction(FroxlorLogger::USR_ACTION, LOG_NOTICE, "viewed customer_extras::htaccess");

		$cperlenabled = Customer::customerHasPerlEnabled($userinfo['customerid']);

		try {
			$htaccess_list_data = include_once dirname(__FILE__) . '/lib/tablelisting/customer/tablelisting.htaccess.php';
			$collection = (new Collection(DirOptions::class, $userinfo))
				->withPagination($htaccess_list_data['htaccess_list']['columns'], $htaccess_list_data['htaccess_list']['default_sorting']);
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}

		UI::view('user/table.html.twig', [
			'listing' => Listing::format($collection, $htaccess_list_data, 'htaccess_list'),
			'actions_links' => [
				[
					'href' => $linker->getLink(['section' => 'extras', 'page' => 'htaccess', 'action' => 'add']),
					'label' => lng('extras.pathoptions_add')
				]
			],
			'entity_info' => lng('extras.description')
		]);
	} elseif ($action == 'delete' && $id != 0) {
		try {
			$json_result = DirOptions::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if (isset($result['customerid']) && $result['customerid'] != '' && $result['customerid'] == $userinfo['customerid']) {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					DirOptions::getLocal($userinfo, $_POST)->delete();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => $page
				]);
			} else {
				HTML::askYesNo('extras_reallydelete_pathoptions', $filename, [
					'id' => $id,
					'page' => $page,
					'action' => $action
				], str_replace($userinfo['documentroot'], '/', $result['path']));
			}
		}
	} elseif ($action == 'add') {
		if (isset($_POST['send']) && $_POST['send'] == 'send') {
			try {
				DirOptions::getLocal($userinfo, $_POST)->add();
			} catch (Exception $e) {
				Response::dynamicError($e->getMessage());
			}
			Response::redirectTo($filename, [
				'page' => $page
			]);
		} else {
			$pathSelect = FileDir::makePathfield($userinfo['documentroot'], $userinfo['guid'], $userinfo['guid']);
			$cperlenabled = Customer::customerHasPerlEnabled($userinfo['customerid']);

			$htaccess_add_data = include_once dirname(__FILE__) . '/lib/formfields/customer/extras/formfield.htaccess_add.php';

			UI::view('user/form.html.twig', [
				'formaction' => $linker->getLink(['section' => 'extras']),
				'formdata' => $htaccess_add_data['htaccess_add']
			]);
		}
	} elseif (($action == 'edit') && ($id != 0)) {
		try {
			$json_result = DirOptions::getLocal($userinfo, [
				'id' => $id
			])->get();
		} catch (Exception $e) {
			Response::dynamicError($e->getMessage());
		}
		$result = json_decode($json_result, true)['data'];

		if ((isset($result['customerid'])) && ($result['customerid'] != '') && ($result['customerid'] == $userinfo['customerid'])) {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					DirOptions::getLocal($userinfo, $_POST)->update();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => $page
				]);
			} else {
				if (strpos($result['path'], $userinfo['documentroot']) === 0) {
					$result['path'] = str_replace($userinfo['documentroot'], "/", $result['path']);
				}
				$cperlenabled = Customer::customerHasPerlEnabled($userinfo['customerid']);

				$result = PhpHelper::htmlentitiesArray($result);

				$htaccess_edit_data = include_once dirname(__FILE__) . '/lib/formfields/customer/extras/formfield.htaccess_edit.php';

				UI::view('user/form.html.twig', [
					'formaction' => $linker->getLink(['section' => 'extras', 'id' => $id]),
					'formdata' => $htaccess_edit_data['htaccess_edit'],
					'editid' => $id
				]);
			}
		}
	}
} elseif ($page == 'backup') {
	// redirect if this customer sub-page is hidden via settings
	if (Settings::IsInList('panel.customer_hide_options', 'extras.backup')) {
		Response::redirectTo('customer_index.php');
	}

	if (Settings::Get('system.backupenabled') == 1) {
		if ($action == 'abort') {
			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				$log->logAction(FroxlorLogger::USR_ACTION, LOG_NOTICE, "customer_extras::backup - aborted scheduled backupjob");
				try {
					CustomerBackups::getLocal($userinfo, $_POST)->delete();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::redirectTo($filename, [
					'page' => $page,
					'action' => ''
				]);
			} else {
				HTML::askYesNo('extras_reallydelete_backup', $filename, [
					'backup_job_entry' => $id,
					'section' => 'extras',
					'page' => $page,
					'action' => $action
				]);
			}
		} elseif ($action == '') {
			$log->logAction(FroxlorLogger::USR_ACTION, LOG_NOTICE, "viewed customer_extras::backup");

			// check whether there is a backup-job for this customer
			try {
				$backup_list_data = include_once dirname(__FILE__) . '/lib/tablelisting/customer/tablelisting.backups.php';
				$collection = (new Collection(CustomerBackups::class, $userinfo));
			} catch (Exception $e) {
				Response::dynamicError($e->getMessage());
			}

			if (isset($_POST['send']) && $_POST['send'] == 'send') {
				try {
					CustomerBackups::getLocal($userinfo, $_POST)->add();
				} catch (Exception $e) {
					Response::dynamicError($e->getMessage());
				}
				Response::standardSuccess('backupscheduled');
			} else {
				$pathSelect = FileDir::makePathfield($userinfo['documentroot'], $userinfo['guid'], $userinfo['guid']);
				$backup_data = include_once dirname(__FILE__) . '/lib/formfields/customer/extras/formfield.backup.php';

				UI::view('user/form-datatable.html.twig', [
					'formaction' => $linker->getLink(['section' => 'extras']),
					'formdata' => $backup_data['backup'],
					'tabledata' => Listing::format($collection, $backup_list_data, 'backup_list'),
				]);
			}
		}
	} else {
		Response::standardError('backupfunctionnotenabled');
	}
}
