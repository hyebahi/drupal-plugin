// js/trustarc.js
(function($, Drupal, once) {
    'use strict';

    var $window = $(window);

    Drupal.trustarc = {};

    const domain = window.location.host;
    const TRUSTARC_ORIGIN = 'https://consent.trustarc.com';
    const trustarc = drupalSettings.trustarc;

    function getBehaviorCookie() {
        if (!window.truste || !window.truste.util || !window.truste.util.readCookie) {
            return '';
        }
        return window.truste.util.readCookie('notice_behavior') || '';
    }

    function getConsentConfig(consentConfig) {
        if (consentConfig === 'consent_model') {
            if (window.truste && window.truste.eu && window.truste.eu.bindMap && window.truste.eu.bindMap.consentModel) {
                return window.truste.eu.bindMap.consentModel;
            }
            return '';
        }

        return getBehaviorCookie();
    }

    function isImpliedLocation(consentSetting) {
        var impliedSettings = trustarc.impliedLocation ? trustarc.impliedLocation : '';

        if (trustarc.consentConfig === 'consent_model' && impliedSettings === '') {
            impliedSettings = 'opt-out';
        }

        if (!consentSetting || !impliedSettings) {
            return false;
        }

        const impliedLocationValues = impliedSettings
            .split(',')
            .map((value) => value.trim().toLowerCase())
            .filter((value) => value !== '');

        return impliedLocationValues.some((location) => consentSetting.toLowerCase().includes(location));
    }

    if (typeof trustarc !== 'undefined' && (trustarc.gcmEnabled || trustarc.standardEventListener)) {
        window.dataLayer = window.dataLayer || [];
    }

    if (typeof trustarc !== 'undefined' && trustarc.gcmEnabled) {
        /* 
        GOOGLE CONSENT MODE INTEGRATION
        */
        const gtag = function() {
            dataLayer.push(arguments);
        }

        gtag('js', new Date());
        gtag('set', 'developer_id.dNTIxZG', true);

        if (trustarc.gaMeasurementID) {
            gtag('config', trustarc.gaMeasurementID);
        }

        gtag('set', 'ads_data_redaction', trustarc.adsDataRedaction == 1);
        gtag('set', 'url_passthrough', trustarc.URLPassthrough == 1);

        // Consent Mode Status
        const ConsentType = {
            DENIED: 'denied',
            GRANTED: 'granted',
        };

        const booleanToConsentStatus = (boolean) => (boolean ? ConsentType.GRANTED : ConsentType.DENIED);

        // Bucket Mapping
        const consentTypesMapped = trustarc.consentTypeMapping || {};


        const getConsentState = (prefCookie) => {
            var consentStates = {};
            var consentSetting = getConsentConfig(trustarc.consentConfig);
            var impliedLocation = isImpliedLocation(consentSetting);

            for (const consentType in consentTypesMapped) {
                var id = parseInt(consentTypesMapped[consentType].trustarc_category_id, 10);
                if(consentType !== "wait_for_update") {
                    if (prefCookie && !prefCookie.includes(0)) {
                        consentStates[consentType] = booleanToConsentStatus(prefCookie.includes(id));
                    } else {
                        consentStates[consentType] = booleanToConsentStatus(impliedLocation);
                    }
                }
            }

            if (consentTypesMapped && consentTypesMapped["wait_for_update"] && consentTypesMapped["wait_for_update"].trustarc_category_id) {
                consentStates['wait_for_update'] = parseInt(consentTypesMapped["wait_for_update"].trustarc_category_id, 10);
            }
            return consentStates;
        };

        var runOnceGCM = 0;
        Drupal.trustarc.handleConsentDecisionForGCM = function(consent) {
            const consentStates = getConsentState(consent.consentDecision);
            var defaultOrUpdate = runOnceGCM === 0 ? 'default' : 'update';
            runOnceGCM++;

            gtag('consent', defaultOrUpdate, consentStates);
        }

        var _taInterval;
        var _taAttempts = 0;
        var _taGoogleTagWasSetLate = function() {
            if (_taAttempts > 50) clearInterval(_taInterval);

            if (window.google_tag_data && window.google_tag_data.ics && window.google_tag_data.ics.wasSetLate) {
                console.warn('WARNING: Tags are firing before consent is initialized. Please ensure that the consent mode default is initialized before firing tags.');
                clearInterval(_taInterval);
            }

            _taAttempts++;
        }
        _taInterval = setInterval(_taGoogleTagWasSetLate, 200);
    }
    /* 
        Event Listener
    */
    var __dispatched__ = {}; // Map of previously dispatched preference levels
    Drupal.trustarc.handleConsentDecisionForTA = function(consent) {
        consent.consentDecision && consent.consentDecision.forEach(function(label) {
            if (!__dispatched__[label]) {
                self.dataLayer && self.dataLayer.push({
                    "event": "GDPR Pref Allows " + label
                });
                __dispatched__[label] = 1;
            }
        });
    }

    var interval = setInterval(() => {
        if (window.truste && truste.cma && truste.cma.callApi) {
            var consentDecision = truste.cma.callApi('getGDPRConsentDecision', domain);
            if (typeof trustarc !== 'undefined') {
                if (trustarc.gcmEnabled) {
                    Drupal.trustarc.handleConsentDecisionForGCM(consentDecision);
                }
                if (trustarc.standardEventListener) {
                    Drupal.trustarc.handleConsentDecisionForTA(consentDecision);
                }
            }
            clearInterval(interval);
        }
    }, 100);

    // Start listening to when users submit their preferences
    window.addEventListener('message', (event) => {
        if (event.origin !== TRUSTARC_ORIGIN) {
            return;
        }

        let eventDataJson = null;
        try {
            if (typeof event.data !== 'string') {
                return;
            }
            eventDataJson = JSON.parse(event.data);
        } catch {
            // Some other event that is not JSON.
        }

        if (eventDataJson && eventDataJson.source === 'preference_manager' && eventDataJson.message === 'submit_preferences') {
            setTimeout(() => {
                var consentDecision = truste.cma.callApi('getGDPRConsentDecision', domain);

                if (typeof trustarc !== 'undefined') {
                    if (trustarc.gcmEnabled) {
                        Drupal.trustarc.handleConsentDecisionForGCM(consentDecision);
                    }
                    if (trustarc.standardEventListener) {
                        Drupal.trustarc.handleConsentDecisionForTA(consentDecision);
                    }
                }
            }, 500);
        }
    }, false);

    function ensureTrustarcContainers() {
        // Ensure banner container exists in body
        var bannerContainer = document.body;
        if (trustarc && trustarc.banner) {
            bannerContainer = document.getElementById(trustarc.banner);
            if (!bannerContainer) {
                bannerContainer = document.createElement('div');
                bannerContainer.id = trustarc.banner;
                document.body.appendChild(bannerContainer);
            }
        }

        // Create teconsent div - use preferencesSelector if defined, otherwise use body
        var teconsntTarget = document.body;
        if (trustarc && trustarc.preferencesSelector) {
            try {
                var selectorElement = document.querySelector(trustarc.preferencesSelector);
                if (selectorElement) {
                    teconsntTarget = selectorElement;
                }
            } catch (e) {
                // Selector error, fall back to body
            }
        }

        if (teconsntTarget && !document.getElementById('teconsent')) {
            var trustarcDiv = document.createElement('div');
            trustarcDiv.id = 'teconsent';
            trustarcDiv.className = 'trustarc-container';
            teconsntTarget.appendChild(trustarcDiv);
        }
    }

    Drupal.trustarc.loadCookieConsent = ensureTrustarcContainers;

    Drupal.trustarc.loadCookieConsent();

})(jQuery, Drupal, once);