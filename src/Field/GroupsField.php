<?php
/*
 * @package     RadicalMart - After Payment User Groups
 * @subpackage  plg_radicalmart_apug
 * @version     __DEPLOY_VERSION__
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2023 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

namespace Joomla\Plugin\RadicalMart\APUG\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;

class GroupsField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var  string
	 *
	 * @since  1.0.0
	 */
	protected $type = 'groups';

	/**
	 * Method to get the field options.
	 *
	 * @throws  \Exception
	 *
	 * @return  array  The field option objects.
	 *
	 * @since  1.0.0
	 */
	protected function getOptions(): array
	{
		$options = parent::getOptions();

		/** @var \Joomla\Plugin\RadicalMart\APUG\Extension\APUG $plugin */
		$plugin = Factory::getApplication()->bootPlugin('apug', 'radicalmart');
		$groups = $plugin->getUserGroups();

		foreach ($groups as $group)
		{
			$option        = new \stdClass();
			$option->value = $group->id;
			$option->text  = $group->title;

			$options[] = $option;
		}

		return $options;
	}
}