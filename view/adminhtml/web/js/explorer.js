/**
 * BroCode GraphQL Explorer — Adminhtml bootstrap for GraphiQL.
 *
 * Reads its configuration from a data attribute rather than an inline script,
 * so the template stays CSP-safe. React, ReactDOM and GraphiQL are vendored
 * UMD builds loaded through RequireJS shims declared in requirejs-config.js.
 */
define([
    'react',
    'react-dom',
    'graphiql'
], function (React, ReactDOM, GraphiQL) {
    'use strict';

    /**
     * Reads the selected store view. Magento's GraphQL layer picks a store from
     * the "Store" header; without it every query silently answers for the
     * default view.
     *
     * @returns {String}
     */
    function currentStore(fallback) {
        var el = document.getElementById('brocode-gqle-store');

        return (el && el.value) || fallback;
    }

    /**
     * Reads the optional customer bearer token.
     *
     * Magento has no admin-token path for GraphQL — an admin token is a REST
     * concept and will not authorise customer-scoped fields here. To query as a
     * customer, mint a token with the generateCustomerToken mutation and paste
     * it in.
     *
     * @returns {String}
     */
    function currentToken() {
        var el = document.getElementById('brocode-gqle-token');

        return el ? el.value.trim() : '';
    }

    /**
     * @param {Object} config
     * @returns {Function}
     */
    function buildFetcher(config) {
        return function (graphQLParams) {
            var headers = {
                'Content-Type': 'application/json',
                'Store': currentStore(config.defaultStore)
            };
            var token = currentToken();

            if (token) {
                headers.Authorization = 'Bearer ' + token.replace(/^Bearer\s+/i, '');
            }

            return fetch(config.endpoint, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(graphQLParams),
                credentials: 'omit'
            }).then(function (response) {
                return response.json().catch(function () {
                    // A non-JSON body is nearly always an HTML error page from
                    // the web server. Surfacing it beats "Unexpected token <".
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
        };
    }

    return function () {
        var root = document.getElementById('brocode-gqle-root'),
            config;

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
                fetcher: buildFetcher(config),
                defaultEditorToolsVisible: true,
                isHeadersEditorEnabled: false,
                defaultQuery: [
                    '# BroCode GraphQL Explorer',
                    '#',
                    '# The Store header follows the store-view selector above, so the same',
                    '# query can be compared across views without editing anything.',
                    '#',
                    '# Ctrl-Space completes. The Docs pane on the right is the live schema.',
                    '',
                    '{',
                    '  storeConfig {',
                    '    store_code',
                    '    store_name',
                    '    locale',
                    '    base_currency_code',
                    '  }',
                    '}',
                    ''
                ].join('\n')
            }),
            root
        );

        // Re-run the active query when the store changes, so switching views is
        // one click rather than click-then-remember-to-execute.
        var storeSelect = document.getElementById('brocode-gqle-store');

        if (storeSelect) {
            storeSelect.addEventListener('change', function () {
                var button = root.querySelector('.graphiql-execute-button');

                if (button) {
                    button.click();
                }
            });
        }
    };
});
