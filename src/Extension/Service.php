<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.service
 *
 * @copyright   (C) 2025 Panagiotis Kiriakopoulos. <https://www.github.com/pnkr>
 * @author      Panagiotis Kiriakopoulos <kiriakopoulos.p@gmail.com>
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

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
 * @since  1.0.0
 */
final class Service extends CMSPlugin implements SubscriberInterface
{
    use SchemaorgPluginTrait;
    use SchemaorgPrepareDateTrait;
    use SchemaorgPrepareImageTrait;

    /**
     * Allowed values for the provider @type
     *
     * @var    string[]
     * @since  1.1.0
     */
    private const PROVIDER_TYPES = ['Person', 'Organization'];

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * The name of the schema form
     *
     * @var   string
     * @since 1.0.0
     */
    // Align pluginName with subform name 'Service' for onSchemaPrepareForm injection
    protected $pluginName = 'Service';

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
     * @since   1.0.0
     */
    public function onSchemaBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $schema = $event->getSchema();

        $graph = $schema->get('@graph') ?? [];

        foreach ($graph as &$entry) {
            if (!\is_array($entry) || ($entry['@type'] ?? '') !== 'Service') {
                continue;
            }

            $entry = $this->prepareService($entry);
        }

        unset($entry);

        $schema->set('@graph', $graph);
    }

    /**
     * Normalize a single Service entry into valid Schema.org output
     *
     * @param   array  $entry  The Service entry
     *
     * @return  array
     *
     * @since   1.1.0
     */
    private function prepareService(array $entry): array
    {
        // Never let generic fields override JSON-LD keywords such as @id, @type or @context
        if (isset($entry['genericField'])) {
            $entry['genericField'] = array_filter(
                $this->toList($entry['genericField']),
                static fn (array $row): bool => !str_starts_with(trim((string) ($row['genericTitle'] ?? '')), '@')
            );
        }

        if (!empty($entry['image'])) {
            $entry['image'] = $this->prepareImage($entry['image']);
        }

        if (isset($entry['provider']) && \is_array($entry['provider'])) {
            $entry['provider'] = $this->prepareProvider($entry['provider']);
        }

        if (isset($entry['brand']) && \is_array($entry['brand'])) {
            $entry['brand']['@type'] = 'Brand';

            if (!empty($entry['brand']['logo'])) {
                $entry['brand']['logo'] = $this->prepareImage($entry['brand']['logo']);
            }
        }

        if (isset($entry['audience']) && \is_array($entry['audience'])) {
            $entry['audience']['@type'] = 'Audience';
        }

        if (isset($entry['aggregateRating']) && \is_array($entry['aggregateRating'])) {
            $entry['aggregateRating']['@type'] = 'AggregateRating';
        }

        // Drop single-object subforms the editor left empty
        foreach (['brand', 'audience', 'aggregateRating'] as $key) {
            if (isset($entry[$key]) && (!\is_array($entry[$key]) || !$this->hasValues($entry[$key]))) {
                unset($entry[$key]);
            }
        }

        if (isset($entry['offers'])) {
            $offers = [];

            foreach ($this->toList($entry['offers']) as $offer) {
                if ($this->hasValues($offer)) {
                    $offers[] = ['@type' => 'Offer'] + $offer;
                }
            }

            $entry['offers'] = $offers;
        }

        // sameAs must be a list of URLs, not a list of {sameAsUrl: ...} objects
        if (isset($entry['sameAs'])) {
            $urls = array_values(array_filter(array_map(
                static fn (array $row): string => trim((string) ($row['sameAsUrl'] ?? '')),
                $this->toList($entry['sameAs'])
            )));

            unset($entry['sameAs']);

            if ($urls) {
                $entry['sameAs'] = $urls;
            }
        }

        // The form field is named "reviews" for backward compatibility; Schema.org uses "review"
        if (isset($entry['reviews'])) {
            $reviews = [];

            foreach ($this->toList($entry['reviews']) as $row) {
                $author = trim((string) ($row['author'] ?? ''));
                $body   = trim((string) ($row['reviewBody'] ?? ''));

                if ($author === '' && $body === '') {
                    continue;
                }

                $review = ['@type' => 'Review'];

                if ($author !== '') {
                    $review['author'] = ['@type' => 'Person', 'name' => $author];
                }

                if ($body !== '') {
                    $review['reviewBody'] = $body;
                }

                $reviews[] = $review;
            }

            unset($entry['reviews']);

            if ($reviews) {
                $entry['review'] = $reviews;
            }
        }

        // serviceLocation is not a Service property; it belongs to ServiceChannel
        if (isset($entry['serviceLocation'])) {
            $address = (array) $entry['serviceLocation'];

            unset($entry['serviceLocation']);

            if ($this->hasValues($address)) {
                $entry['availableChannel'] = [
                    '@type'           => 'ServiceChannel',
                    'serviceLocation' => [
                        '@type'   => 'Place',
                        'address' => ['@type' => 'PostalAddress'] + $address,
                    ],
                ];
            }
        }

        // keywords is not a Service property; drop values saved by older versions
        unset($entry['keywords']);

        return $entry;
    }

    /**
     * Normalize the provider (Person or Organization)
     *
     * @param   array  $provider  The provider data
     *
     * @return  array
     *
     * @since   1.1.0
     */
    private function prepareProvider(array $provider): array
    {
        // The form stores lowercase values ("person", "organization")
        $type = ucfirst(strtolower((string) ($provider['@type'] ?? '')));

        $provider['@type'] = \in_array($type, self::PROVIDER_TYPES, true) ? $type : 'Person';

        if ($provider['@type'] === 'Organization' && !empty($provider['logo']['url'])) {
            $provider['logo']['@type'] = 'ImageObject';
            $provider['logo']['url']   = $this->prepareImage($provider['logo']['url']);
        } else {
            // Person has no logo property
            unset($provider['logo']);
        }

        if (isset($provider['address']) && \is_array($provider['address'])) {
            $provider['address']['@type'] = 'PostalAddress';
        }

        return $provider;
    }

    /**
     * Convert a repeatable subform value into a list of rows
     *
     * @param   mixed  $value  The subform value
     *
     * @return  array[]
     *
     * @since   1.1.0
     */
    private function toList($value): array
    {
        if (!\is_array($value) && !\is_object($value)) {
            return [];
        }

        return array_values(array_map(static fn ($row): array => (array) $row, (array) $value));
    }

    /**
     * Check whether an object has any non-empty value besides JSON-LD keywords
     *
     * @param   array  $data  The data to check
     *
     * @return  boolean
     *
     * @since   1.1.0
     */
    private function hasValues(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (!str_starts_with((string) $key, '@') && $value !== '' && $value !== null && $value !== []) {
                return true;
            }
        }

        return false;
    }
}
