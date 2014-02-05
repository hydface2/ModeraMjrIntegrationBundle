<?php

namespace Modera\BackendDashboardBundle\Contributions;

use Doctrine\ORM\EntityManager;
use Modera\BackendDashboardBundle\Dashboard\DashboardInterface;
use Modera\BackendDashboardBundle\Entity\GroupSettings;
use Modera\BackendDashboardBundle\Entity\UserSettings;
use Modera\JSRuntimeIntegrationBundle\Config\ConfigMergerInterface;
use Modera\SecurityBundle\Entity\User;
use Sli\ExpanderBundle\Ext\ContributorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Modera\JSRuntimeIntegrationBundle\Config\CallbackConfigMerger;

/**
 * Adds dashboard list to config for backend. It allows
 * to show dashboards immediately without loading remote data through Direct.
 *
 * @author    Alex Rudakov <alexandr.rudakov@modera.org>
 * @copyright 2014 Modera Foundation
 */
class ConfigMergersProvider implements ContributorInterface, ConfigMergerInterface
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * @var \Sli\ExpanderBundle\Ext\ContributorInterface
     */
    private $dashboardProvider;

    /**
     * @param ContainerInterface   $container         Symfony container for isAllowed() method
     * @param ContributorInterface $dashboardProvider Dashboard providers are collected automatically by Expander bundle
     */
    public function __construct(ContainerInterface $container, ContributorInterface $dashboardProvider)
    {
        $this->container = $container;
        $this->dashboardProvider = $dashboardProvider;
    }

    /**
     * Merge in dashboard list into runtime configuration.
     *
     * @param array $currentConfig
     *
     * @return array
     */
    public function merge(array $currentConfig)
    {
        list($default, $userDashboards) = $this->getUserDashboards();

        $result = array();
        foreach ($this->dashboardProvider->getItems() as $dashboard) {
            /* @var DashboardInterface $dashboard */

            if (!$dashboard->isAllowed($this->container)) {
                continue;
            }

            if (!in_array($dashboard->getName(), $userDashboards)) {
                continue;
            }

            $result[] = array(
                'name' => $dashboard->getName(),
                'label' => $dashboard->getLabel(),
                'uiClass' => $dashboard->getUiClass(),
                'default' => $dashboard->getName() == $default
            );
        }

        return array_merge($currentConfig, array(
                'modera_backend_dashboard' => array(
                    'dashboards' => $result
                )
            ));
    }

    /**
     * @inheritDoc
     *
     * @return array
     */
    public function getItems()
    {
        return array($this);
    }

    /**
     * Return container
     *
     * @return mixed
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Return dashboardProvider
     *
     * @return mixed
     */
    public function getDashboardProvider()
    {
        return $this->dashboardProvider;
    }

    public function getUserDashboards()
    {
        /** @var EntityManager $em */
        $em = $this->container->get('doctrine.orm.entity_manager');

        /** @var User $user */
        $user = $this->container->get('security.context')->getToken()->getUser();

        $settings = [];
        foreach($user->getGroups() as $group) {
            /** @var GroupSettings $groupSettings */
            $groupSettings = $em->getRepository(GroupSettings::clazz())->findOneBy(['group' => $group]);
            if ($groupSettings) {
                $settings[] = $groupSettings->getDashboardSettings();
            }
        }
        /** @var UserSettings $userSettings */
        $userSettings = $em->getRepository(UserSettings::clazz())->findOneBy(['user' => $user]);
        if ($userSettings) {
            $settings[] = $userSettings->getDashboardSettings();
        }

        $dashboards = [];
        $defaults = [];

        foreach($settings as $data) {
            $dashboards = array_merge($dashboards, $data['hasAccess']);
            if ($data['defaultDashboard']) {
                $defaults[] = $data['defaultDashboard'];
            }
        }
        $default = count($defaults) ? $defaults[count($defaults) - 1] : $dashboards[0];

        return [$default, $dashboards];
    }
}