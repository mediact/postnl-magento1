/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) 2014 Total Internet Group B.V. (http://www.tig.nl)
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
document.observe('dom:loaded', function(){

    if(null !== document.getElementById('postnl_support'))
    {
        function toStep(rel)
        {
            // switch tabs
            $$('#postnl-wizard .section-config').each(function(elem){
                elem.hide();
            });

            $(rel).show().up().show();

            // switch wizard nav active state
            $$('#postnl-wizard ul a').each(function(elem){
                elem.className = '';
            });
            $$('a[rel="'+rel+'"]')[0].className = 'active';
            return false;
        }

        // show the support tab
        var supportTab = document.getElementById('postnl_support');
        supportTab.show();

        $('postnl_support-state').setValue(1);
        $('postnl_support-head').up().hide();

        // create the wizard
        var postnlWizard = document.createElement('div');
        postnlWizard.id = 'postnl-wizard';
        postnlWizard.className = 'section-config';

        var postnlWizardFieldset = document.createElement('div');
        postnlWizardFieldset.addClassName('fieldset');
        postnlWizard.appendChild(postnlWizardFieldset);

        supportTab.parentNode.insertBefore(postnlWizard, supportTab.nextSibling);

        // move 5 existing config sections into the wizard
        var wizardSectionConfigs = $$('.section-config.postnl-wizard');

        wizardSectionConfigs.each(function(element) {
            postnlWizardFieldset.appendChild(element);
        });

        // add navigation
        var postnlWizardNavigation = document.createElement('ul'),
            postnlWizardList = document.createElement('li'),
            postnlWizardLink = document.createElement('a');

        var step = 0;
        $$('#postnl-wizard .section-config .entry-edit-head a').each(function(elem){
            var listClone = postnlWizardList.cloneNode(),
                linkClone = postnlWizardLink.cloneNode();

            step++;
            linkClone.href = '#wizard' + step;
            linkClone.rel = elem.id.replace('-head', '');
            linkClone.onclick = function(){
                toStep(this.rel);
            };
            linkClone.innerHTML = step + '. ' + elem.innerHTML;

            listClone.appendChild(linkClone);
            postnlWizardNavigation.appendChild(listClone);
        });

        postnlWizardFieldset.insertBefore(postnlWizardNavigation, postnlWizardFieldset.firstChild);

        // init active tab
        postnlWizard.select('.section-config fieldset').invoke('hide');
        postnlWizard.select('.section-config fieldset')[0].show();
        postnlWizard.select('ul a')[0].className = 'active';

        // create the advanced settings group
        var postnlAdvanced = document.createElement('div');
        postnlAdvanced.id = 'postnl-advanced';
        postnlAdvanced.className = 'section-config';

        var postnlAdvancedFieldset = document.createElement('fieldset');
        postnlAdvancedFieldset.id = 'postnl_advanced';
        postnlAdvancedFieldset.hide();
        postnlAdvanced.appendChild(postnlAdvancedFieldset);

        postnlWizard.parentNode.insertBefore(postnlAdvanced, postnlWizard.nextSibling);

        // move all other sections to the advanced settings group
        $$('.section-config:not(.postnl-wizard, .postnl-support, #postnl-advanced, #postnl-wizard)').each(function(element) {
            postnlAdvancedFieldset.appendChild(element);
        });

        // advanced group header
        var postnlAdvancedHeader = document.createElement('div'),
            postnlAdvancedLink = postnlWizardLink.cloneNode();

        postnlAdvancedHeader.className = 'entry-edit-head collapseable';
        postnlAdvancedLink.innerHTML = 'Advanced settings'; // TODO: translate
        postnlAdvancedLink.id = 'postnl_advanced-head';
        postnlAdvancedLink.href = '#';
        postnlAdvancedLink.onclick = function() {
            Fieldset.toggleCollapse('postnl_advanced', '');
            return false;
        };
        postnlAdvancedHeader.appendChild(postnlAdvancedLink);

        postnlAdvancedFieldset.parentNode.insertBefore(postnlAdvancedHeader, postnlAdvancedFieldset);

        // hash navigation
        function toHash()
        {
            var target = $$('a[href="' + window.location.hash + '"]')[0];
            toStep(target.rel);
        }
        $$('#postnl-wizard ul a[href^="#"]').each(function(elem){
            Event.observe(elem, 'click', function(){
                var hash = elem.href;
                if(window.history.pushState) {
                    window.history.pushState(null, null, hash);
                    toHash();
                } else {
                    window.location.hash = hash;
                }

            });
        });
        window.onhashchange = toHash;
        window.onload = toHash;

        // wrap radio buttons with labels
        $$('#postnl-wizard input[type="radio"]').each(function(elem){
            var wrapper = document.createElement('div');
            wrapper.className = 'wrapper-radio';

            elem.parentNode.insertBefore(wrapper, elem);
            wrapper.appendChild(elem);
            wrapper.appendChild(wrapper.next());
        });
        // remove leftover spaces after wrapping the elements
        $$('.wrapper-radio').each(function(elem){
            if(elem.parentNode)
            {
                elem.parentNode.innerHTML = elem.parentNode.innerHTML.replace(/&nbsp;/g, ' ');
            }
        });
    }
});