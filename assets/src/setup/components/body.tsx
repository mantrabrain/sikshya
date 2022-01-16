import {Container, extendTheme, ChakraProvider} from "@chakra-ui/react";
import * as React from 'react';
import {StepsStyleConfig as Steps} from 'chakra-ui-steps';

import ClickableSteps from "./parts/step";

const theme = extendTheme({
	components: {
		Steps,
	},
	initialColorMode: 'dark',


});
const Body = () => {

	return (
		<ChakraProvider theme={theme} >
			<Container>
				<ClickableSteps/>
			</Container>
		</ChakraProvider>
	);
};
export default Body
