<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.service
 *
 * @copyright   (C) 2025 Panagiotis Kiriakopoulos. <https://www.github.com/pnkr>
 * @author      Panagiotis Kiriakopoulos <kiriakopoulos.p@gmail.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Pnkr\Plugin\Schemaorg\Service\Extension\Service;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   5.1.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Service(
                    (array) PluginHelper::getPlugin('schemaorg', 'service')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
