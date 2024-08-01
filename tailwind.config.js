import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
	daisyui: {
		themes: ["nord", "dark"
			// {
			// 	dark: {	
			// 		"primary": "#60a5fa",	
			// 		"primary-content": "#030a15",	
			// 		"secondary": "#1e3a8a",	
			// 		"secondary-content": "#cdd6e9",	
			// 		"accent": "#c084fc",	
			// 		"accent-content": "#0e0616",	
			// 		"neutral": "#111827",	
			// 		"neutral-content": "#c9cbcf",	
			// 		"base-100": "#1c2e38",	
			// 		"base-200": "#17272f",	
			// 		"base-300": "#121f27",	
			// 		"base-content": "#cdd1d4",	
			// 		"info": "#0284c7",	
			// 		"info-content": "#00060e",	
			// 		"success": "#00a900",	
			// 		"success-content": "#000a00",	
			// 		"warning": "#facc15",	
			// 		"warning-content": "#150f00",	
			// 		"error": "#b91c1c",	
			// 		"error-content": "#f7d5d1",	
			// 	},  
			// },
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
	darkMode: ['class', '[data-theme="dark"]'],
    plugins: [
		forms,
		typography,
		require("daisyui")
	],
};
