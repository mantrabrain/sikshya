import {Container, extendTheme, ChakraProvider} from "@chakra-ui/react";
import {StepsStyleConfig as Steps} from 'chakra-ui-steps';

const Theme = extendTheme({
	components: {
		Steps,
	},
	initialColorMode: 'dark',
	size: {
		container: "900px"
	},
	styles: {
		global: {
			// styles for the `body`
			body: {
				bg: 'gray.400',
				color: 'white',
			},
			// styles for the `a`
			a: {
				color: 'teal.500',
				_hover: {
					textDecoration: 'underline',
				},
			},
		},
	},


});

export Theme
