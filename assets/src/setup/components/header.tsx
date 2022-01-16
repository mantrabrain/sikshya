import {Container, Center, ChakraProvider} from "@chakra-ui/react";
import * as React from 'react';
import {StepsStyleConfig as Steps} from 'chakra-ui-steps';

import ClickableSteps from "./parts/step";

const Header = () => {

	return (

		<Container p={50}>
			<Center>

				<h1>Sikshya WordPress LMS Plugin Header</h1>
			</Center>
		</Container>
	);
};
export default Header
