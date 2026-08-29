import terser from "@rollup/plugin-terser";

const config = [
	{
		input: './src/Hooks.js',
		output: {
			file: './dst/JavaScriptHooks.min.js',
			format: 'es',
			sourcemap: false,
		},
		plugins: [
			terser(),
		]
	},
];

// noinspection JSUnusedGlobalSymbols
export default config;
