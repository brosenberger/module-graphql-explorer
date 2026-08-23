# Vendored third-party assets

Committed rather than pulled at runtime so the module works offline, needs no
build step, and does not hand a CDN a request from your Adminhtml.

| File | Upstream | Version | Licence |
|---|---|---|---|
| `graphiql.umd.js` | [graphiql](https://www.npmjs.com/package/graphiql) `dist/index.umd.js` | 3.9.0 | MIT |
| `graphiql.css` | graphiql `dist/style.css` | 3.9.0 | MIT |
| `react.min.js` | [react](https://www.npmjs.com/package/react) `umd/react.production.min.js` | 18.3.1 | MIT |
| `react-dom.min.js` | [react-dom](https://www.npmjs.com/package/react-dom) `umd/react-dom.production.min.js` | 18.3.1 | MIT |

GraphiQL 3.x is pinned deliberately: 4.x and 5.x are ESM-only and expect a
bundler plus web workers, which would force a build toolchain into a module
whose whole point is that it drops into an existing Magento install.

Refresh with:

```bash
npm pack graphiql@3.9.0 react@18.3.1 react-dom@18.3.1
```

then copy the four files above and update this table.
