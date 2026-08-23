# BroCode GraphQL Explorer

An embedded [GraphiQL](https://github.com/graphql/graphiql) IDE inside the Magento 2
Adminhtml, with a store-view switcher wired to Magento's GraphQL `Store` header.

Magento ships a GraphQL endpoint and no way to explore it. You end up in Postman,
hand-writing headers, guessing at the schema, and forgetting that a query answers
differently per store view. This puts the schema, autocompletion and live docs
behind an ACL-protected admin page, and makes switching store views one dropdown.

![GraphQL Explorer in the Magento admin](docs/screenshot.png)

## What it does

- **Full GraphiQL 3** — schema-aware autocompletion (Ctrl-Space), live docs pane,
  query history, prettify, variables editor.
- **Store-view switcher** — sets the `Store` request header, which is how Magento
  selects a store view for GraphQL. Changing it re-runs the current query, so the
  same query can be compared across views without editing anything.
- **Optional customer token** — paste a bearer token to query as a customer.
- **ACL-protected** — `BroCode_GraphQlExplorer::explorer`, under
  *System → Other Settings*.
- **No CDN, no build step** — React and GraphiQL are vendored UMD builds served
  from the module's own `view/adminhtml/web`. Works offline and hands no third
  party a request from your Adminhtml.

## Install

```bash
composer require brocode/module-graphql-explorer
bin/magento module:enable BroCode_GraphQlExplorer
bin/magento setup:upgrade
bin/magento setup:static-content:deploy -a adminhtml   # production mode only
bin/magento cache:flush
```

Then **System → Other Settings → GraphQL Explorer**.

## Two things about Magento's GraphQL auth

Worth knowing before you file a bug against this module:

**There is no admin token for GraphQL.** Admin bearer tokens are a REST concept.
Magento provides no mutation that mints one for GraphQL, so this page queries as a
guest unless you supply a *customer* token — get one from `generateCustomerToken`:

```graphql
mutation {
  generateCustomerToken(email: "customer@example.com", password: "…") {
    token
  }
}
```

**The `Store` header is not honoured everywhere.** For most queries it selects the
store view correctly. On some customer-scoped operations Magento resolves the store
from the token instead and ignores the header
([magento/graphql-ce#770](https://github.com/magento/graphql-ce/issues/770)). If a
customer-scoped query ignores your switcher, that is upstream behaviour, not this
module.

## Requirements

Magento 2.4.x, PHP 8.1–8.4. No other dependencies.

## Configuration

None. The endpoint URL is derived from the default store's base URL rather than the
admin URL, because the admin may live on its own domain where `/graphql` does not
exist.

## CSP

`etc/csp_whitelist.xml` allows `'self'` for `style-src` and `connect-src`, and
`blob:` for `worker-src`, which is what the editor needs. No external host is
whitelisted — everything is served locally by design.

## Third-party assets

Vendored deliberately. See [`VENDOR-ASSETS.md`](VENDOR-ASSETS.md) for versions,
licences (all MIT) and how to refresh them.

## Licence

MIT — see [LICENSE](LICENSE).

Built by [Benjamin Rosenberger](https://brocode.at). If it saved you an afternoon,
[buy me a coffee](https://www.buymeacoffee.com/brosenberger).
