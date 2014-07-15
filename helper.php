<?php defined('_JEXEC') or die;

/**
 * File       helper.php
 * Created    8/6/13 3:41 PM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/Menu-Wrench/issues
 * Copyright  Copyright (C) 2013-2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v2 or later
 */

jimport('joomla.application.menu');

class modMenuwrenchHelper
{

	/**
	 * Constructor
	 *
	 * @param JRegistry $params : module parameters
	 *
	 * @since 0.1
	 *
	 */
	public function __construct($params)
	{
		$this->app    = JFactory::getApplication();
		$this->db     = JFactory::getDbo();
		$this->menu   = $this->app->getMenu();
		$this->active = $this->menu->getActive();
		$this->params = $params;
	}

	/**
	 * Retrieves all menu items, sorts, combines, mixes, stirs, and purges what we want in a logical order
	 *
	 * @return mixed
	 * @since 0.1
	 *
	 */
	function getBranches()
	{
		$renderedItems = $this->params->get('renderedItems', 0);
		$showCategoryItems = $this->params->get('showCategoryItems', 0);
		$showSubmenu   = $this->params->get('showSubmenu', 1);
		$hideSubmenu   = $this->params->get('hideSubmenu', 0);
		// http://stackoverflow.com/questions/3787669/how-to-get-specific-menu-items-from-joomla/10218419#10218419
		$items = $this->menu->getItems(null, null);

		// Convert renderedItems to an array if only one item is selected
		if (!is_array($renderedItems))
		{
			$renderedItems = str_split($renderedItems, strlen($renderedItems));
		}

		if (!is_array($hideSubmenu))
		{
			$hideSubmenu = str_split($hideSubmenu, strlen($hideSubmenu));
		}

		/**
		 * Builds menu hierarchy by nesting children in parent object's 'children' property.
		 * First builds an item Id based array, then discards old nodes.
		 */
		foreach ($items as $key => $item)
		{

			$items[$item->id] = $item;

			// If menu item is a category, add all articles as menu items
			if ($showCategoryItems && array_key_exists('view', $item->query) && $item->query['view'] === 'category')
			{
				$items[$item->id]->children = $this->linkCategoryItems(
					$this->getCategoryItems($item->query['id']),
					$item->query['id'],
					$item->id
				);
			}

			unset($items[$key]);

			if ($item->parent_id != 1)
			{
				$items[$item->parent_id]->children[$item->id] = $item;
			}
		}

		foreach ($items as $key => $item)
		{

			/**
			 * Remove non-selected menu item objects
			 * At this point, all selected items to render are in the first level of the array
			 */
			if (!in_array($key, $renderedItems))
			{
				unset($items[$key]);
			}

			/**
			 * Builds object classes
			 */
			$item->class = 'item' . $item->id . ' ' . $item->alias;

			// Add parent class to all parents
			if (isset($item->children))
			{
				$item->class .= ' parent';
			}

			// Add current class to specific item
			if ($item->id == $this->active->id)
			{
				$item->class .= ' current';
			}

			// Add active class to all items in active branch
			if (in_array($item->id, $this->active->tree))
			{
				$item->class .= ' active';
			}

			// Hide sub-menu items if parameter set to no and parent not active
			if ((!in_array($item->id, $this->active->tree) && $showSubmenu == 0) || in_array($item->id, $hideSubmenu))
			{
				unset($item->children);
			}
		}

		return $items;
	}

	/**
	 * Get all of the published articles in a given category
	 *
	 * @param $categoryId
	 *
	 * @return mixed
	 */
	private function getCategoryItems($categoryId)
	{

		$query = $this->db->getQuery(true);

		$query
			->select($this->db->quoteName(array('id', 'alias', 'title')))
			->from($this->db->quoteName('#__content'))
			->where($this->db->quoteName('state') . ' = ' . $this->db->quote('1'), ' AND ')
			->where($this->db->quoteName('catid') . ' = ' . $this->db->quote($categoryId))
			->order('ordering ASC');

		// Reset the query using our newly populated query object.
		$this->db->setQuery($query);

		// Load the results as a list of stdClass objects (see later for more options on retrieving data).
		return $this->db->loadObjectList();
	}

	/**
	 * Generate single article item link, based on supplied parameters
	 *
	 * @param $items
	 * @param $catId
	 * @param $itemId
	 *
	 * @return mixed
	 */
	private function linkCategoryItems($items, $catId, $itemId)
	{
		foreach ($items as $item)
		{
			$item->link = 'index.php?option=com_content&view=article&id=' . $item->id . ':' . $item->alias . '&catid=' . $catId . '&Itemid=' . $itemId;
		}

		return $items;

	}

	/**
	 * Renders the menu
	 *
	 * @param        $item           : the menu item
	 * @param string $containerTag   : optional, declare a different container HTML element
	 * @param string $containerClass : optional, declare a different container class
	 * @param string $itemTag        : optional, declare a different menu item HTML element
	 * @param int    $level          : counter for level of depth that is rendering.
	 *
	 * @return string
	 *
	 * @since 0.1
	 */

	public function render($item, $containerTag = '<ul>', $containerClass = 'menu', $itemTag = '<li>', $level = 0)
	{
		// Force object property creation as they don't exist for category blog items
		$item->browserNav = isset($item->browserNav) ? $item->browserNav : '';
		$item->class      = isset($item->class) ? $item->class : '';
		$item->type       = isset($item->type) ? $item->type : '';

		$itemOpenTag       = str_replace('>', ' class="' . $item->class . '">', $itemTag);
		$itemCloseTag      = str_replace('<', '</', $itemTag);
		$containerOpenTag  = str_replace('>', ' class="' . $containerClass . '">', $containerTag);
		$containerCloseTag = str_replace('<', '</', $containerTag);
		$renderDepth       = htmlspecialchars($this->params->get('renderDepth', 10));

		switch ($item->browserNav) :
			default:
			case 0:
				$browserNav = '';
				break;
			case 1:
				$browserNav = 'target="_blank"';
				break;
			case 2:
				$browserNav = "onclick=\"window.open(this.href,'targetWindow','toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,'" . $this->params->get('window_open') . ");return false;\"";
				break;
		endswitch;

		switch ($item->type)
		{
			case 'alias':
				$output = $itemOpenTag . '<a ' . $browserNav . ' href="index.php?Itemid=' . $item->params->get('aliasoptions') . '"/>' . $item->title . '</a>';
				break;

			case 'separator':
				$output = $itemOpenTag . '<span class="separator">' . $item->title . '</span>';
				break;

			case 'url' :
				if ((strpos($item->link, 'index.php?') === 0) && (strpos($item->link, 'Itemid=') === false))
				{
					$output = $itemOpenTag . '<a ' . $browserNav . ' href="' . JRoute::_($item->link . '&Itemid=' . $item->id) . '"/>' . $item->title . '</a>';
				}
				else
				{
					$output = $itemOpenTag . '<a ' . $browserNav . ' href="' . $item->link . '"/>' . $item->title . '</a>';
				}
				break;

			default:
				$item->link = strpos($item->link, 'Itemid') ? $item->link : $item->link . '&Itemid=' . $item->id;
				$output     = $itemOpenTag . '<a ' . $browserNav . ' href="' . JRoute::_($item->link) . '"/>' . $item->title . '</a>';
				break;
		}

		$level++;

		if (isset($item->children) && $level <= $renderDepth)
		{

			$output .= $containerOpenTag;

			foreach ($item->children as $item)
			{

				$output .= $this->render($item, $containerTag, $containerClass, $itemTag, $level);
			}
			$output .= $itemCloseTag;
			$output .= $containerCloseTag;
		}

		$output .= $itemCloseTag;

		return $output;
	}
}
