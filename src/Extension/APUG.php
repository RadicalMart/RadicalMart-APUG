<?php
/*
 * @package     RadicalMart 1C Integration
 * @subpackage  plg_radicalmart_1c
 * @version     1.0.0
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2023 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

namespace Joomla\Plugin\RadicalMart\APUG\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper as RadicalMartParamsHelper;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\ParamsHelper as RadicalMartExpressParamsHelper;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Utilities\ArrayHelper;

class APUG extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Loads the application object.
	 *
	 * @var  \Joomla\CMS\Application\CMSApplication
	 *
	 * @since  1.0.0
	 */
	protected $app = null;

	/**
	 * Loads the database object.
	 *
	 * @var  \Joomla\Database\DatabaseDriver
	 *
	 * @since  1.0.0
	 */
	protected $db = null;

	/**
	 * Plugins forms path.
	 *
	 * @var    string
	 *
	 * @since  1.0.0
	 */
	protected string $formsPath = JPATH_PLUGINS . '/radicalmart/apug/forms';

	/**
	 * Plugins forms path.
	 *
	 * @var    array|null
	 *
	 * @since  1.0.0
	 */
	protected ?array $_usersGroups = null;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onRadicalMartPrepareConfigForm'      => 'onPrepareConfigForm',
			'onRadicalMartPrepareProductForm'     => 'onPrepareProductForm',
			'onRadicalMartAfterChangeOrderStatus' => 'onAfterChangeOrderStatus',
			'onRadicalMartGetOrderLogs'           => 'onGetOrderLogs',

			'onRadicalMartExpressPrepareConfigForm'      => 'onPrepareConfigForm',
			'onRadicalMartExpressPrepareProductForm'     => 'onPrepareProductForm',
			'onRadicalMartExpressAfterChangeOrderStatus' => 'onAfterChangeOrderStatus',
			'onRadicalMartExpressGetOrderLogs'           => 'onGetOrderLogs',
		];
	}

	/**
	 * Method to load RadicalMart & RadicalMart Express configuration form.
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @throws \Exception
	 *
	 * @since 1.0.0
	 */
	public function onPrepareConfigForm(Form $form, $data = [])
	{
		$component = $this->app->input->getCmd('component');
		if ($component === 'com_radicalmart')
		{
			$form->loadFile($this->formsPath . '/radicalmart/config.xml');
		}
		elseif ($component === 'com_radicalmart_express')
		{
			$form->loadFile($this->formsPath . '/radicalmart_express/config.xml');
		}
	}

	/**
	 * Method to load RadicalMart & RadicalMart Express product form.
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @throws \Exception
	 *
	 * @since 1.0.0
	 */
	public function onPrepareProductForm(Form $form, $data = [])
	{
		$formName = $form->getName();
		if ($formName === 'com_radicalmart.product')
		{
			$form->loadFile($this->formsPath . '/radicalmart/product.xml');
		}
		elseif ($formName === 'com_radicalmart_express.product')
		{
			$form->loadFile($this->formsPath . '/radicalmart_express/product.xml');
		}
	}

	/**
	 * Method to change user groups.
	 *
	 * @param   string  $context    Context selector string.
	 * @param   object  $order      Order object.
	 * @param   int     $oldStatus  Old status id.
	 * @param   int     $newStatus  New status id.
	 * @param   bool    $isNew      Is new order.
	 *
	 * @throws \Exception
	 *
	 * @since 1.0.0
	 */
	public function onAfterChangeOrderStatus(string $context, object $order, int $oldStatus, int $newStatus, bool $isNew)
	{
		$user_id = (int) $order->created_by;
		if (empty($user_id))
		{
			return;
		}
		$model    = false;
		$params   = false;
		$statuses = false;

		// Get component utils
		if (strpos($context, 'com_radicalmart.') !== false)
		{
			/* @var \Joomla\Component\RadicalMart\Administrator\Model\OrderModel $model */
			$model    = Factory::getApplication()->bootComponent('com_radicalmart')->getMVCFactory()
				->createModel('Order', 'Administrator');
			$params   = RadicalMartParamsHelper::getComponentParams();
			$statuses = ArrayHelper::toInteger($params->get('apug_statuses', []));
		}
		elseif (strpos($context, 'com_radicalmart_express.') !== false)
		{
			/* @var \Joomla\Component\RadicalMartExpress\Administrator\Model\OrderModel $model */
			$model    = Factory::getApplication()->bootComponent('com_radicalmart_express')->getMVCFactory()
				->createModel('Order', 'Administrator');
			$params   = RadicalMartExpressParamsHelper::getComponentParams();
			$statuses = [2];
		}

		if ($model && !empty($statuses))
		{
			// Get actions
			$canInsertGroups = (in_array($newStatus, $statuses));
			$canDeleteGroups = (!$canInsertGroups && (int) $params->get('apug_delete', 0) === 1);
			if (!$canInsertGroups && !$canDeleteGroups)
			{
				return;
			}

			// Get current user groups
			$db            = $this->db;
			$query         = $db->getQuery(true)
				->select('group_id')
				->from($db->quoteName('#__user_usergroup_map'))
				->where($db->quoteName('user_id') . ' = :user_id')
				->bind(':user_id', $user_id, ParameterType::INTEGER);
			$currentGroups = $db->setQuery($query)->loadColumn();

			// Prepare changes
			$insertGroups = [];
			$deleteGroups = [];
			foreach ($order->products as $product)
			{
				$groups = ArrayHelper::toInteger($product->plugins->get('apug', []));
				foreach ($groups as $group)
				{
					if ($canInsertGroups && !in_array($group, $currentGroups))
					{
						$insertGroups[] = $group;
					}

					if ($canDeleteGroups && in_array($group, $currentGroups))
					{
						$deleteGroups[] = $group;
					}
				}
			}

			// Insert user groups
			if (!empty($insertGroups))
			{
				$insert          = new \stdClass();
				$insert->user_id = $user_id;
				foreach ($insertGroups as $group)
				{
					$insert->group_id = $group;

					$db->insertObject('#__user_usergroup_map', $insert);
				}

				// Add log
				$model->addLog($order->id, 'apug_insert', [
					'plugin'  => 'apug',
					'group'   => 'radicalmart',
					'groups'  => $insertGroups,
					'user_id' => -1
				]);
			}

			// Delete user groups
			if (!empty($deleteGroups))
			{
				$query = $db->getQuery(true)
					->delete($db->quoteName('#__user_usergroup_map'))
					->whereIn('group_id', $deleteGroups)
					->where($db->quoteName('user_id') . ' = :user_id')
					->bind(':user_id', $user_id, ParameterType::INTEGER);
				$db->setQuery($query)->execute();

				// Add log
				$model->addLog($order->id, 'apug_delete', [
					'plugin'  => 'apug',
					'group'   => 'radicalmart',
					'groups'  => $deleteGroups,
					'user_id' => -1
				]);
			}
		}
	}

	/**
	 * Method to display logs in RadicalMart & RadicalMart Express order.
	 *
	 * @param   string  $context  Context selector string.
	 * @param   array   $log      Log data.
	 *
	 * @since  1.0.0
	 */
	public function onGetOrderLogs(string $context, array &$log)
	{
		if (!in_array($log['action'], ['apug_insert', 'apug_delete']))
		{

			return;
		}

		$userGroups = $this->getUserGroups();

		$logGroups       = [];
		$logGroupsTitles = [];


		foreach ($log['groups'] as $group_id)
		{
			if (isset($userGroups[$group_id]))
			{
				$userGroup         = $userGroups[$group_id];
				$logGroups[]       = $userGroup;
				$logGroupsTitles[] = $userGroup->title;
			}
		}

		$log['action_text'] = Text::_('PLG_RADICALMART_APUG_LOGS_' . $log['action']);
		$log['message']     = implode(', ', $logGroupsTitles);
		$log['groups']      = $logGroups;
	}

	/**
	 * Method to get users groups.
	 *
	 * @return array Users groups data array.
	 *
	 * @since 1.0.0
	 */
	public function getUserGroups(): array
	{
		if ($this->_usersGroups === null)
		{
			$db                 = $this->db;
			$query              = $db->getQuery(true)
				->select(['id', 'title'])
				->from($db->quoteName('#__usergroups'))
				->order('lft asc');
			$this->_usersGroups = $db->setQuery($query)->loadObjectList('id');
		}

		return $this->_usersGroups;
	}
}