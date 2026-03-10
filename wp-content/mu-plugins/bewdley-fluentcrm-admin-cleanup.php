<?php
/**
 * Plugin Name: Bewdley FluentCRM Admin Cleanup
 * Description: Hides FluentCRM promo widgets for Shop Manager and removes Powered By footer branding from campaign emails.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Temporary test mode: apply cleanup rules to all logged-in accounts.
if (!defined('BEWDLEY_FCRM_APPLY_ALL_USERS')) {
    define('BEWDLEY_FCRM_APPLY_ALL_USERS', true);
}

/**
 * True when current user is a Shop Manager and not an administrator/super admin.
 *
 * @return bool
 */
function bewdley_fcrm_is_shop_manager_user()
{
    if (!is_user_logged_in()) {
        return false;
    }

    if (BEWDLEY_FCRM_APPLY_ALL_USERS) {
        return true;
    }

    if (is_super_admin() || current_user_can('manage_options')) {
        return false;
    }

    $user = wp_get_current_user();
    if (!$user || empty($user->roles) || !is_array($user->roles)) {
        return false;
    }

    if (in_array('shop_manager', $user->roles, true)) {
        return true;
    }

    // Fallback: users with shop-management capability but without admin capability.
    return current_user_can('manage_woocommerce');
}

/**
 * Check FluentCRM admin page context.
 *
 * @return bool
 */
function bewdley_fcrm_is_fluentcrm_admin_page()
{
    if (!is_admin()) {
        return false;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

    return $page === 'fluentcrm-admin';
}

/**
 * Remove recommendation payload and optional quick links from dashboard API data.
 */
add_filter('fluent_crm/dashboard_data', function ($data) {
    if (!bewdley_fcrm_is_shop_manager_user()) {
        return $data;
    }

    if (!is_array($data)) {
        return $data;
    }

    // Disable the top recommendation promo card.
    $data['recommendation'] = false;

    return $data;
}, 20);

/**
 * Remove "Get Pro" top-menu entry from FluentCRM for Shop Manager-level users.
 */
add_filter('fluent_crm/menu_items', function ($menuItems) {
    if (!bewdley_fcrm_is_shop_manager_user() || !is_array($menuItems)) {
        return $menuItems;
    }

    $blockedKeys = ['forms', 'funnels', 'automations', 'reports', 'settings'];

    $filtered = array_filter($menuItems, function ($item) use ($blockedKeys) {

        if (!is_array($item)) {
            return true;
        }

        $key = isset($item['key']) ? (string) $item['key'] : '';
        $class = isset($item['class']) ? (string) $item['class'] : '';
        $permalink = isset($item['permalink']) ? (string) $item['permalink'] : '';

        if (in_array($key, $blockedKeys, true)) {
            return false;
        }

        if ($key === 'get_pro' || $class === 'pro_link') {
            return false;
        }

        if (stripos($permalink, 'utm_campaign=pro') !== false) {
            return false;
        }

        return true;
    });

    // Keep only selected entries under the top "Emails" dropdown.
    $allowedEmailSubKeys = ['all_campaigns', 'email_templates', 'all_emails'];

    $filtered = array_map(function ($item) use ($allowedEmailSubKeys) {
        if (!is_array($item)) {
            return $item;
        }

        $key = isset($item['key']) ? (string) $item['key'] : '';
        if ($key !== 'campaigns' || empty($item['sub_items']) || !is_array($item['sub_items'])) {
            return $item;
        }

        $item['sub_items'] = array_values(array_filter($item['sub_items'], function ($subItem) use ($allowedEmailSubKeys) {
            if (!is_array($subItem)) {
                return false;
            }

            $subKey = isset($subItem['key']) ? (string) $subItem['key'] : '';
            return in_array($subKey, $allowedEmailSubKeys, true);
        }));

        return $item;
    }, $filtered);

    return array_values($filtered);
}, 20);

/**
 * Remove pro links from full sidebar menu payload for Shop Manager-level users.
 */
add_filter('fluent_crm/full_sidebar_menu_items', function ($menuItems) {
    if (!bewdley_fcrm_is_shop_manager_user() || !is_array($menuItems)) {
        return $menuItems;
    }

    $blockedKeys = ['forms', 'funnels', 'automations', 'reports', 'settings'];

    $filtered = array_filter($menuItems, function ($item) use ($blockedKeys) {

        if (!is_array($item)) {
            return true;
        }

        $key = isset($item['key']) ? (string) $item['key'] : '';
        $uri = isset($item['uri']) ? (string) $item['uri'] : '';

        if (in_array($key, $blockedKeys, true)) {
            return false;
        }

        if ($key === 'get_pro') {
            return false;
        }

        if (stripos($uri, 'utm_campaign=pro') !== false) {
            return false;
        }

        return true;
    });

    // Keep only selected entries under the sidebar "Emails" parent.
    $allowedSidebarEmailKeys = ['campaigns', 'templates', 'all_emails'];

    $filtered = array_map(function ($item) use ($allowedSidebarEmailKeys) {
        if (!is_array($item) || empty($item['children']) || !is_array($item['children'])) {
            return $item;
        }

        $menuTitle = isset($item['menu_title']) ? (string) $item['menu_title'] : '';
        $pageTitle = isset($item['page_title']) ? (string) $item['page_title'] : '';
        $isEmailsParent = stripos($menuTitle, 'email') !== false || stripos($pageTitle, 'email') !== false;

        if (!$isEmailsParent) {
            return $item;
        }

        $item['children'] = array_values(array_filter($item['children'], function ($child) use ($allowedSidebarEmailKeys) {
            if (!is_array($child)) {
                return false;
            }

            $childKey = isset($child['key']) ? (string) $child['key'] : '';
            return in_array($childKey, $allowedSidebarEmailKeys, true);
        }));

        return $item;
    }, $filtered);

    return array_values($filtered);
}, 20, 2);

/**
 * Hide remaining promo and review cards rendered by the SPA sidebar widgets.
 */
add_action('admin_head', function () {
    if (!bewdley_fcrm_is_shop_manager_user() || !bewdley_fcrm_is_fluentcrm_admin_page()) {
        return;
    }

    echo '<style id="bewdley-fluentcrm-admin-cleanup">';
    echo '.fluentcrm_main_menu_items,.fc_menu_items{display:none !important;}';
    echo '.fc_quick_links,.fc_quick_links_wrap,ul.fc_quick_links{display:none !important;}';
    echo '.fluentcrm_admin_dashboard .fc_request_review_widget{display:none !important;}';
    echo '.fluentcrm_admin_dashboard .fc_m_20.fc_request_review_widget{display:none !important;}';
    echo '.fluentcrm_admin_dashboard .fc_m_20.fc_request_review_widget.fc_quick_links{display:none !important;}';
    echo '#fluentcrm_app .fluentcrm_admin_dashboard > .el-row > .el-col.el-col-24.el-col-sm-24.el-col-md-8.el-col-lg-6{display:none !important;}';
    echo 'a.pro_link,.fc_key_get_pro,[href*="utm_campaign=pro"],[href*="/add-ons"]{display:none !important;}';
    echo '</style>';
});

add_action('admin_footer', function () {
    if (!bewdley_fcrm_is_shop_manager_user() || !bewdley_fcrm_is_fluentcrm_admin_page()) {
        return;
    }

    ?>
    <script id="bewdley-fluentcrm-admin-cleanup-js">
        (function () {
            function hideBlockedLinks() {
                var appMenus = document.querySelectorAll('.fluentcrm_main_menu_items, .fc_menu_items');
                appMenus.forEach(function (menu) {
                    if (menu && menu.style) {
                        menu.style.setProperty('display', 'none', 'important');
                    }
                });

                var blockedPhrases = [
                    'quick links',
                    'contact segments',
                    'recurring campaigns',
                    'email sequences',
                    'documentations',
                    'video tutorials',
                    'smtp/mail settings',
                    'smtp settings'
                ];

                var blockedHrefParts = [
                    'contact-groups/dynamic-segments',
                    'email/recurring-campaigns',
                    'email/sequences',
                    'settings/smtp_settings',
                    'docs.fluentcrm.com',
                    'fluentcrm.com/docs',
                    'youtube.com',
                    'youtu.be'
                ];

                var links = document.querySelectorAll('a, .el-menu-item, .el-submenu__title, h3, h4');
                links.forEach(function (node) {
                    var text = (node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
                    var href = ((node.getAttribute && node.getAttribute('href')) || '').toLowerCase();

                    var textBlocked = blockedPhrases.some(function (phrase) {
                        return text === phrase || text.indexOf(phrase) !== -1;
                    });

                    var hrefBlocked = blockedHrefParts.some(function (part) {
                        return href.indexOf(part) !== -1;
                    });

                    if (!textBlocked && !hrefBlocked) {
                        return;
                    }

                    var block = node.closest('li, .el-menu-item, .fc_m_20, .fc_quick_links, .fc_quick_links_wrap');
                    if (block && block.style) {
                        block.style.setProperty('display', 'none', 'important');
                    }

                    if (node.style) {
                        node.style.setProperty('display', 'none', 'important');
                    }
                });
            }

            function hidePromoCards() {
                hideBlockedLinks();

                var root = document.querySelector('#fluentcrm_app .fluentcrm_admin_dashboard') || document.querySelector('.fluentcrm_admin_dashboard');
                if (!root) {
                    return;
                }

                var rightColumn = root.querySelector(':scope > .el-row > .el-col.el-col-24.el-col-sm-24.el-col-md-8.el-col-lg-6');
                if (rightColumn) {
                    rightColumn.style.display = 'none';
                }

                var rightColumns = root.querySelectorAll('.el-col-md-8.el-col-lg-6, .el-col.el-col-24.el-col-sm-24.el-col-md-8.el-col-lg-6');
                rightColumns.forEach(function (col) {
                    var quickLinkCards = col.querySelectorAll('.fc_m_20.fc_quick_links');
                    quickLinkCards.forEach(function (card, index) {
                        if (index > 0) {
                            card.style.display = 'none';
                        }
                    });
                });

                var reviewWidgets = root.querySelectorAll('.fc_request_review_widget');
                reviewWidgets.forEach(function (widget) {
                    widget.style.display = 'none';
                });

                var cards = root.querySelectorAll('.fc_m_20, .fc_quick_links, .fluentcrm_databox');
                cards.forEach(function (card) {
                    var cardText = (card.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
                    var hasPromoText = (
                        cardText.indexOf('upgrade to pro') !== -1 ||
                        cardText.indexOf('upgrade to pro now') !== -1 ||
                        cardText.indexOf('do more with woocommerce + fluentcrm') !== -1 ||
                        (cardText.indexOf('do more with') !== -1 && cardText.indexOf('fluentcrm pro') !== -1) ||
                        cardText.indexOf('hi shop manager') !== -1 ||
                        cardText.indexOf('hi immy') !== -1 ||
                        cardText.indexOf('hi ') === 0 ||
                        cardText.indexOf('love this plugin') !== -1 ||
                        cardText.indexOf('write a review') !== -1
                    );
                    var hasPromoLink = !!card.querySelector('a[href*="utm_campaign=pro"], a[href*="fluentcrm.com/"]');

                    if (hasPromoText || hasPromoLink) {
                        card.style.display = 'none';
                    }
                });

                // Explicitly hide greeting/review cards by heading text.
                var headingNodes = root.querySelectorAll('.fluentcrm_header_title, .fc_request_review_header h4, h4, h3');
                headingNodes.forEach(function (node) {
                    var text = (node.textContent || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
                    var isGreeting = text.indexOf('hi ') === 0;
                    var isReview = text.indexOf('love this plugin') !== -1;

                    if (!isGreeting && !isReview) {
                        return;
                    }

                    var card = node.closest('.fc_m_20, .fc_quick_links, .fc_request_review_widget, .fluentcrm_databox, .fluentcrm_body');
                    if (card) {
                        card.style.display = 'none';
                    }
                });

                var getProLinks = document.querySelectorAll(
                    '#fluentcrm_app a.pro_link, #fluentcrm_app .fc_key_get_pro, #fluentcrm_app a[href*="utm_campaign=pro"], #fluentcrm_app a[href*="/add-ons"]'
                );

                getProLinks.forEach(function (link) {
                    var menuItem = link.closest('li');
                    if (menuItem) {
                        menuItem.style.display = 'none';
                    }
                    link.style.display = 'none';
                });
            }

            hidePromoCards();

            var observer = new MutationObserver(function () {
                hidePromoCards();
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            // Extra guard against Vue redraws.
            var safetyRuns = 0;
            var safetyTimer = setInterval(function () {
                hidePromoCards();
                hideBlockedLinks();
                safetyRuns++;
                if (safetyRuns > 60) {
                    clearInterval(safetyTimer);
                }
            }, 500);

            document.addEventListener('mouseover', hideBlockedLinks, true);
            window.addEventListener('resize', hideBlockedLinks);

            // FluentCRM refreshes dashboard data periodically; run again after load.
            setTimeout(hidePromoCards, 1200);
            setTimeout(hidePromoCards, 3000);
            setTimeout(hideBlockedLinks, 1200);
            setTimeout(hideBlockedLinks, 3000);
        })();
    </script>
    <?php
});

/**
 * Strip FluentCRM Powered By footer line from rendered campaign email templates.
 *
 * @param string $emailBody
 * @return string
 */
function bewdley_fcrm_strip_powered_by_footer($emailBody)
{
    if (!is_string($emailBody) || $emailBody === '') {
        return $emailBody;
    }

    $patterns = [
        '#<p>\s*Powered\s+By\s*<a[^>]*fluentcrm\.com/\?utm_source=wp&utm_medium=wp_mail&utm_campaign=footer[^>]*>.*?</a>\s*</p>#is',
        '#<p>\s*Powered\s+By\s*<a[^>]*fluentcrm\.com[^>]*>.*?</a>\s*</p>#is'
    ];

    return preg_replace($patterns, '', $emailBody);
}

add_filter('fluent_crm/email-design-template-plain', 'bewdley_fcrm_strip_powered_by_footer', 100, 3);
add_filter('fluent_crm/email-design-template-simple', 'bewdley_fcrm_strip_powered_by_footer', 100, 3);
add_filter('fluent_crm/email-design-template-classic', 'bewdley_fcrm_strip_powered_by_footer', 100, 3);
add_filter('fluent_crm/email-design-template-raw_classic', 'bewdley_fcrm_strip_powered_by_footer', 100, 3);
add_filter('fluent_crm/email-design-template-web_preview', 'bewdley_fcrm_strip_powered_by_footer', 100, 3);
