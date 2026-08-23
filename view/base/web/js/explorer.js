/**
 * BroCode GraphQL Explorer — bootstraps GraphiQL in Adminhtml and on the
 * optional public route.
 *
 * Configuration arrives in a data attribute rather than an inline script, so
 * the templates stay CSP-safe. React, ReactDOM and GraphiQL are vendored UMD
 * builds loaded through the RequireJS shims in view/base/requirejs-config.js.
 */
define([
    'react',
    'react-dom',
    'graphiql'
], function (React, ReactDOM, GraphiQL) {
    'use strict';

    /**
     * A default query that is actually worth running.
     *
     * products() is guest-accessible, so this returns data with no token. It is
     * also deliberately store-sensitive: price currency and product names are
     * per store view, so switching views visibly changes the result rather than
     * just the request.
     *
     * Note the sku/name filters: in 2.4.x `sku` is a FilterEqualTypeInput
     * (eq / in), not a match filter — search() is the free-text entry point.
     */
    var DEFAULT_QUERY = [
        '# BroCode GraphQL Explorer',
        '#',
        '# Ctrl-Space completes. The Docs pane on the right is the live schema.',
        '# products() needs no authentication — change the search term and run it.',
        '#',
        '# Switching the store view changes the endpoint and the Store header,',
        '# so prices and names come back for that view.',
        '',
        '{',
        '  products(search: "shirt", pageSize: 5, currentPage: 1) {',
        '    total_count',
        '    page_info { current_page total_pages }',
        '    items {',
        '      sku',
        '      name',
        '      stock_status',
        '      url_key',
        '      price_range {',
        '        minimum_price {',
        '          final_price { value currency }',
        '          discount { percent_off }',
        '        }',
        '      }',
        '      small_image { url label }',
        '    }',
        '  }',
        '}',
        ''
    ].join('\n');

    /**
     * @param {Object} config
     * @returns {Object}
     */
    function selectedStore(config) {
        var el = document.getElementById('brocode-gqle-store'),
            code = (el && el.value) || config.currentStore,
            i;

        for (i = 0; i < config.stores.length; i++) {
            if (config.stores[i].code === code) {
                return config.stores[i];
            }
        }

        return { code: code, endpoint: config.endpoint };
    }

    /**
     * @returns {String}
     */
    function currentToken() {
        var el = document.getElementById('brocode-gqle-token');

        return el ? el.value.trim().replace(/^Bearer\s+/i, '') : '';
    }

    /**
     * @param {Object} config
     * @param {Object} params
     * @param {String} [tokenOverride]
     * @returns {Promise}
     */
    function post(config, params, tokenOverride) {
        var store = selectedStore(config),
            headers = {
                'Content-Type': 'application/json',
                'Store': store.code
            },
            token = typeof tokenOverride === 'string' ? tokenOverride : currentToken();

        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        return fetch(store.endpoint, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(params),
            credentials: 'omit'
        }).then(function (response) {
            return response.json().catch(function () {
                // A non-JSON body is nearly always an HTML error page from the
                // web server. Surfacing it beats "Unexpected token <".
                return response.text().then(function (text) {
                    return {
                        errors: [{
                            message: 'Non-JSON response (HTTP ' + response.status + ')',
                            extensions: { body: text.slice(0, 2000) }
                        }]
                    };
                });
            });
        });
    }

    /**
     * Keeps the read-only endpoint field in step with the store selector.
     *
     * @param {Object} config
     */
    function syncEndpointDisplay(config) {
        var field = document.getElementById('brocode-gqle-endpoint');

        if (field) {
            field.value = selectedStore(config).endpoint;
        }
    }

    /**
     * Clears the bearer token so subsequent queries run as a guest again.
     *
     * Worth having as one click: a stale or expired token turns every
     * customer-scoped query into an authorisation error, and the fix is not
     * obvious when the field is a long opaque string you stopped reading.
     */
    function wireTokenReset() {
        var button = document.getElementById('brocode-gqle-token-clear'),
            field = document.getElementById('brocode-gqle-token');

        if (!button || !field) {
            return;
        }

        button.addEventListener('click', function () {
            var had = field.value.trim() !== '';

            field.value = '';

            var form = document.getElementById('brocode-gqle-token-form');

            if (form) {
                form.open = false;
            }

            showMessage('notice', had
                ? 'Token cleared. Queries now run as a guest.'
                : 'No token was set — queries already run as a guest.');
        });
    }

    /**
     * Copies text, flashing the button to confirm.
     *
     * navigator.clipboard is unavailable on plain HTTP, which a local or
     * staging store may well be, so a textarea + execCommand path stays as a
     * fallback rather than leaving the button silently dead.
     *
     * @param {String} text
     * @param {HTMLElement} button
     */
    function copyText(text, button) {
        function done() {
            button.classList.add('brocode-gqle__copy--done');
            window.setTimeout(function () {
                button.classList.remove('brocode-gqle__copy--done');
            }, 1200);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () {
                legacyCopy(text, done);
            });

            return;
        }

        legacyCopy(text, done);
    }

    /**
     * @param {String} text
     * @param {Function} done
     */
    function legacyCopy(text, done) {
        var scratch = document.createElement('textarea');

        scratch.value = text;
        scratch.setAttribute('readonly', 'readonly');
        scratch.style.position = 'fixed';
        scratch.style.opacity = '0';
        document.body.appendChild(scratch);
        scratch.select();
        scratch.setSelectionRange(0, text.length);

        try {
            if (document.execCommand('copy')) {
                done();
            }
        } catch (e) {
            // Nothing useful to do; the user can still select and copy by hand.
        }

        document.body.removeChild(scratch);
    }

    /**
     * Copy buttons: the endpoint field, and the token mutation snippet.
     */
    function wireCopyButton() {
        var endpointBtn = document.getElementById('brocode-gqle-endpoint-copy'),
            endpoint = document.getElementById('brocode-gqle-endpoint'),
            mutationBtn = document.getElementById('brocode-gqle-token-mutation-copy'),
            mutation = document.getElementById('brocode-gqle-token-mutation-code');

        if (endpointBtn && endpoint) {
            endpointBtn.addEventListener('click', function () {
                copyText(endpoint.value, endpointBtn);
            });
        }

        if (mutationBtn && mutation) {
            mutationBtn.addEventListener('click', function () {
                copyText(mutation.textContent, mutationBtn);
            });
        }
    }

    /**
     * The "?" beside Request token, revealing the mutation it actually runs.
     */
    function wireTokenHelp() {
        var button = document.getElementById('brocode-gqle-token-help'),
            panel = document.getElementById('brocode-gqle-token-mutation');

        if (!button || !panel) {
            return;
        }

        button.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            button.setAttribute('aria-expanded', String(!panel.hidden));
        });
    }

    /**
     * Renders one Magento-style message into the status row.
     *
     * Built as DOM nodes rather than innerHTML: the text can include a message
     * echoed back by the server, and that is not something to interpolate into
     * markup.
     *
     * @param {String} type  error | success | notice
     * @param {String} text
     */
    function showMessage(type, text) {
        var host = document.getElementById('brocode-gqle-token-status'),
            wrapper,
            inner;

        if (!host) {
            return;
        }

        host.textContent = '';

        if (!text) {
            return;
        }

        wrapper = document.createElement('div');
        wrapper.className = 'message message-' + type + ' ' + type;

        inner = document.createElement('div');
        inner.setAttribute('data-ui-id', 'messages-message-' + type);
        inner.textContent = text;

        wrapper.appendChild(inner);
        host.appendChild(wrapper);
    }

    /**
     * Inline generateCustomerToken, so a token can be obtained without leaving
     * the page. Magento has no admin-token path for GraphQL, which is why a
     * customer login is the only way to reach customer-scoped fields.
     *
     * @param {Object} config
     */
    function wireTokenGenerator(config) {
        var button = document.getElementById('brocode-gqle-token-get');

        // The wrapper is a native <details>, so disclosure needs no JavaScript.
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            var email = (document.getElementById('brocode-gqle-email') || {}).value,
                password = (document.getElementById('brocode-gqle-password') || {}).value;

            if (!email || !password) {
                    showMessage('error', 'Email and password are both required.');

                return;
            }

            showMessage('notice', 'Requesting a token…');
            button.disabled = true;

            post(config, {
                query: 'mutation($email:String!,$password:String!){generateCustomerToken(email:$email,password:$password){token}}',
                variables: { email: email, password: password }
            }, '').then(function (result) {
                var token = result && result.data && result.data.generateCustomerToken &&
                    result.data.generateCustomerToken.token;

                if (token) {
                    document.getElementById('brocode-gqle-token').value = token;
                    showMessage('success', 'Token set. Customer tokens are valid for one hour by default.');
                    // Do not keep the password sitting in the DOM afterwards.
                    document.getElementById('brocode-gqle-password').value = '';
                } else {
                    showMessage('error', (result.errors && result.errors[0] && result.errors[0].message) ||
                        'No token returned. Check the email, the password, and the selected store view.');
                }
            }).catch(function (e) {
                showMessage('error', 'Request failed: ' + String(e));
            }).then(function () {
                button.disabled = false;
            });
        });
    }

    return function () {
        var root = document.getElementById('brocode-gqle-root'),
            config,
            storeSelect;

        if (!root) {
            return;
        }

        try {
            config = JSON.parse(root.getAttribute('data-gqle-config'));
        } catch (e) {
            root.className += ' brocode-gqle__error';
            root.textContent = 'GraphQL Explorer: could not read configuration.';

            return;
        }

        ReactDOM.render(
            React.createElement(GraphiQL, {
                fetcher: function (params) {
                    return post(config, params);
                },
                defaultEditorToolsVisible: true,
                isHeadersEditorEnabled: false,
                defaultQuery: DEFAULT_QUERY
            }),
            root
        );

        syncEndpointDisplay(config);
        wireTokenGenerator(config);
        wireCopyButton();
        wireTokenReset();
        wireTokenHelp();

        // The public route renders its own selector; Adminhtml uses the native
        // store switcher, which reloads the page instead.
        storeSelect = document.getElementById('brocode-gqle-store');

        if (storeSelect) {
            storeSelect.addEventListener('change', function () {
                var execute = root.querySelector('.graphiql-execute-button');

                syncEndpointDisplay(config);

                if (execute) {
                    execute.click();
                }
            });
        }
    };
});
