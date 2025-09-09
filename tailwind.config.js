import typography from '@tailwindcss/typography';

const commonThemes = {
	"--rounded-box": ".5rem",          
	"--rounded-btn": ".35rem",        
	"--rounded-badge": ".35rem",
	"--tab-radius": ".5rem",
	"--animation-input": "0.15s",
}

/** @type {import('tailwindcss').Config} */
export default {
	daisyui: {
		themes: [
			{
				dark: {
					...commonThemes,
					"primary": "#78716c",
					"primary-content": "#e3e1e0",
					"secondary": "#7a7a7a",
					"secondary-content": "#d1d1d5",
					"accent": "#8c9ae3",
					"accent-content": "#d3d4dd",
					"neutral": "#302f3c",
					"neutral-content": "#d1d1d5",
					"base-100": "#20202A",
					"base-200": "#1a1a23",
					"base-300": "#15151c",
					"base-content": "#cecdd0",
					"info": "#1e40af",
					"info-content": "#ced9f2",
					"success": "#166534",
					"success-content": "#d1dfd3",
					"warning": "#a16207",
					"warning-content": "#eddfd1",
					"error": "#991b1b",
					"error-content": "#efd3cf"
				},
				light: {
					...commonThemes,
					"primary": "#d6d3d1",
					"primary-content": "#101010",
					"secondary": "#9ca3af",
					"secondary-content": "#090a0b",
					"accent": "#525783",
					"accent-content": "#110c16",
					"neutral": "#6b7280",
					"neutral-content": "#e0e1e4",
					"base-100": "#fafafa",
					"base-200": "#f1f2f3", 
					"base-300": "#e8eaed",
					"base-content": "#161616",
					"info": "#60a5fa",
					"info-content": "#030a15",
					"success": "#10b981",
					"success-content": "#000d06",
					"warning": "#fb923c",
					"warning-content": "#150801",
					"error": "#ef4444",
					"error-content": "#140202",
				},

			},
		],
		darkTheme: "dark",
	},
	content: [
		'./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
		'./vendor/laravel/jetstream/**/*.blade.php',
		'./storage/framework/views/*.php',
		'./resources/views/**/*.blade.php',
		'./vendor/robsontenorio/mary/src/View/Components/**/*.php',
	],
	theme: {
		fontFamily: {
			sans: ['Inter', 'sans-serif'],
		},
	},
	darkMode: ['selector', '[data-theme="dark"]'],
	plugins: [
		typography,
		require("daisyui")
	],
};
