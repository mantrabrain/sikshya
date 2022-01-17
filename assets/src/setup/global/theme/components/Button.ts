const Button = {

	// 1. We can update the base styles
	baseStyle: {
		fontWeight: 'normal', // Normally, it is "semibold"
	},
	// 2. We can add a new button size or extend existing
	sizes: {
		xl: {
			h: '56px',
			fontSize: 'lg',
			px: '32px',
		},
	},
	// 3. We can add a new visual variant
	variants: {
		'with-shadow': {
			bg: 'red.400',
			boxShadow: '0 0 2px 2px #efdfde',
		},

	},

};

export default Button
