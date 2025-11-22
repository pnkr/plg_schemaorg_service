<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.service
 *
 * @copyright   (C) 2025 Panagiotis Kiriakopoulos. <https://www.github.com/pnkr>
 * @author      Panagiotis Kiriakopoulos <kiriakopoulos.p@gmail.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Pnkr\Plugin\Schemaorg\Service\Extension;

use Joomla\CMS\Event\Plugin\System\Schemaorg\BeforeCompileHeadEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Schemaorg\SchemaorgPluginTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareDateTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareImageTrait;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Schemaorg Plugin
 *
 * @since  5.1.0
 */
final class Service extends CMSPlugin implements SubscriberInterface
{
    use SchemaorgPluginTrait;
    use SchemaorgPrepareDateTrait;
    use SchemaorgPrepareImageTrait;

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  5.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * The name of the schema form
     *
     * @var   string
     * @since 5.1.0
     */
    // Align pluginName with subform name 'Service' for onSchemaPrepareForm injection
    protected $pluginName = 'Service';

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   5.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onSchemaPrepareForm'       => 'onSchemaPrepareForm',
            'onSchemaBeforeCompileHead' => ['onSchemaBeforeCompileHead', Priority::BELOW_NORMAL],
        ];
    }

    /**
     * Cleanup all Service types
     *
     * @param   BeforeCompileHeadEvent  $event  The given event
     *
     * @return  void
     *
     * @since   5.1.0
     */
    public function onSchemaBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $schema = $event->getSchema();

        $graph = $schema->get('@graph');

        foreach ($graph as &$entry) {
            if (!isset($entry['@type']) || $entry['@type'] !== 'Service') {
                continue;
            }

            // Normalize main service image
            if (!empty($entry['image'])) {
                $entry['image'] = $this->prepareImage($entry['image']);
            }

            // Fix provider @type capitalization (ensure Organization/Person is capitalized)
            if (!empty($entry['provider']['@type'])) {
                $entry['provider']['@type'] = ucfirst(strtolower($entry['provider']['@type']));
            }

            // Normalize provider logo image URL
            if (!empty($entry['provider']['logo']['url'])) {
                $entry['provider']['logo']['url'] = $this->prepareImage($entry['provider']['logo']['url']);
            }

            // Normalize brand logo image URL (remove joomlaImage artifacts)
            if (!empty($entry['brand']['logo'])) {
                // Remove joomlaImage:// artifacts if present
                $brandLogo = $entry['brand']['logo'];
                if (strpos($brandLogo, '#joomlaImage') !== false) {
                    $brandLogo = strstr($brandLogo, '#', true);
                }
                $entry['brand']['logo'] = $this->prepareImage($brandLogo);
            }
        }

        $schema->set('@graph', $graph);
    }
}
