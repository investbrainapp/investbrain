import defaultTheme from 'tailwindcss/defaultTheme';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
	daisyui: {
		themes: [
			{
				business: {
					...require("daisyui/src/theming/themes")["business"],
					primary: "#8C5364",
					secondary: "#939BB0",
					"--animation-input": "0.15s",
					"hover": '#00000000'
				},
			},
			{
				corporate: {
					...require("daisyui/src/theming/themes")["corporate"],
					primary: "#75A1D5",
					secondary: "#91A3B0",
					"--animation-input": "0.15s"
				},
			},
		]
	},
    content: [
		'./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
		 './vendor/laravel/jetstream/**/*.blade.php',
		 './storage/framework/views/*.php',
		 './resources/views/**/*.blade.php',
		 "./vendor/robsontenorio/mary/src/View/Components/**/*.php"
	],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
	darkMode: ['class', '[data-theme="business"]'],
    plugins: [
		typography,
		require("daisyui")
	],
};
