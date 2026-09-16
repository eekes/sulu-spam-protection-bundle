<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Symfony\DependencyInjection;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Everything a project might reasonably want to change. The defaults are what a Sulu site needs,
 * so installing the bundle and adding the field to a form is enough.
 */
final class Configuration implements ConfigurationInterface
{
    public const string ROOT_NODE = 'eekes_sulu_spam_protection';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NODE);

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('table_name')
                    ->defaultValue(BlockedSubmission::DEFAULT_TABLE_NAME)
                    ->cannotBeEmpty()
                    ->info('Table the blocked submissions are stored in. Changing it after the first migration needs a rename migration.')
                ->end()
                ->integerNode('retention_days')
                    ->defaultValue(90)
                    ->min(0)
                    ->info('How long a blocked submission is kept by the prune command. 0 keeps everything forever.')
                ->end()
                ->booleanNode('store_payload')
                    ->defaultTrue()
                    ->info('Stores what the sender filled in, so a submission blocked by mistake can still be followed up on. Switch off to log only the technical context.')
                ->end()
                ->booleanNode('anonymize_ip')
                    ->defaultFalse()
                    ->info('Masks the host part of the IP address before storing it. Enough to recognise a network, not enough to identify a visitor.')
                ->end()
                ->scalarNode('field_title')
                    ->defaultValue('Spam protection')
                    ->cannotBeEmpty()
                    ->info('Name of the field editors add to a Sulu form. Sulu does not translate form field metadata, so this is literal text.')
                ->end()
                ->scalarNode('field_xml_path')
                    ->defaultNull()
                    ->info('Overrides the form field XML of the Sulu field type. Defaults to the one shipped with the bundle.')
                ->end()
                ->scalarNode('success_redirect')
                    ->defaultValue('?send=true')
                    ->cannotBeEmpty()
                    ->info('What a blocked Sulu form submission is answered with. Must be the same page a genuine visitor gets.')
                ->end()
                ->booleanNode('register_form_theme')
                    ->defaultTrue()
                    ->info('Adds the reCAPTCHA form theme of this bundle to twig.form_themes, so the widget also works inside a Live Component.')
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Registers the list of blocked submissions under Settings in the Sulu admin.')
                        ->end()
                        ->integerNode('navigation_position')
                            ->defaultValue(40)
                            ->info('Position of the navigation item within Settings.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
