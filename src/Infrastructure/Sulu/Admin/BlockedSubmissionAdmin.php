<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Infrastructure\Sulu\Admin;

use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * Puts the log of blocked submissions under Settings, so what the spam check throws away stays
 * visible without a database client.
 *
 * The detail page only opens for users who may delete: a blocked submission is a log entry that is
 * never edited, so its only toolbar action is deleting it - and a form view without any toolbar
 * action is refused by the administration interface. Everything except the answers the visitor
 * typed is already in the list, which is what a view-only user gets.
 */
final class BlockedSubmissionAdmin extends Admin
{
    public const string LIST_VIEW = 'eekes_spam_protection.blocked_submissions.list';
    public const string EDIT_FORM_VIEW = 'eekes_spam_protection.blocked_submissions.edit_form';
    public const string EDIT_FORM_DETAILS_VIEW = 'eekes_spam_protection.blocked_submissions.edit_form.details';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly int $navigationPosition,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->securityChecker->hasPermission(BlockedSubmission::SULU_SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $navigationItem = new NavigationItem('eekes_spam_protection.blocked_submissions');
        $navigationItem->setPosition($this->navigationPosition);
        $navigationItem->setView(self::LIST_VIEW);

        $navigationItemCollection->get(Admin::SETTINGS_NAVIGATION_ITEM)->addChild($navigationItem);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(BlockedSubmission::SULU_SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $mayDelete = $this->securityChecker->hasPermission(
            BlockedSubmission::SULU_SECURITY_CONTEXT,
            PermissionTypes::DELETE,
        );

        $listView = $this->viewBuilderFactory
            ->createListViewBuilder(self::LIST_VIEW, '/spam-protection/blocked-submissions')
            ->setResourceKey(BlockedSubmission::SULU_RESOURCE_KEY)
            ->setListKey(BlockedSubmission::SULU_LIST_KEY)
            ->setTitle('eekes_spam_protection.blocked_submissions')
            ->addListAdapters(['table'])
        ;

        if (!$mayDelete) {
            $viewCollection->add($listView->disableSelection());

            return;
        }

        $viewCollection->add(
            $listView
                ->setEditView(self::EDIT_FORM_VIEW)
                ->addToolbarActions([new ToolbarAction('sulu_admin.delete')]),
        );

        $viewCollection->add(
            $this->viewBuilderFactory
                ->createResourceTabViewBuilder(self::EDIT_FORM_VIEW, '/spam-protection/blocked-submissions/:id')
                ->setResourceKey(BlockedSubmission::SULU_RESOURCE_KEY)
                ->setBackView(self::LIST_VIEW)
                ->setTitleProperty('sourceName'),
        );

        $viewCollection->add(
            $this->viewBuilderFactory
                ->createFormViewBuilder(self::EDIT_FORM_DETAILS_VIEW, '/details')
                ->setResourceKey(BlockedSubmission::SULU_RESOURCE_KEY)
                ->setFormKey(BlockedSubmission::SULU_FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->addToolbarActions([new ToolbarAction('sulu_admin.delete')])
                ->setParent(self::EDIT_FORM_VIEW),
        );
    }

    /**
     * @return array<string, array<string, array<string, string[]>>>
     */
    public function getSecurityContexts()
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Settings' => [
                    BlockedSubmission::SULU_SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
