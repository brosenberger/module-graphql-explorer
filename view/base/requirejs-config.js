/**
 * Shims for the vendored UMD builds. GraphiQL expects React and ReactDOM as
 * globals, hence the explicit dependency order and exports.
 */
var config = {
    paths: {
        'react': 'BroCode_GraphQlExplorer/vendor/react.min',
        'react-dom': 'BroCode_GraphQlExplorer/vendor/react-dom.min',
        'graphiql': 'BroCode_GraphQlExplorer/vendor/graphiql.umd'
    },
    shim: {
        'react': {
            exports: 'React'
        },
        'react-dom': {
            deps: ['react'],
            exports: 'ReactDOM'
        },
        'graphiql': {
            deps: ['react', 'react-dom'],
            exports: 'GraphiQL'
        }
    }
};
