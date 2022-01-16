import {Container, extendTheme, Center} from "@chakra-ui/react";
import * as React from 'react';
import {StepsStyleConfig as Steps} from 'chakra-ui-steps';

const theme = extendTheme({
	components: {
		Steps,
	},
});
const Footer = () => {

	return (

		<Container p={50}>
			<Center>
				<h1>Sikshya WordPress LMS Plugin Footer</h1>
			</Center>
		</Container>
	);
};
export default Footer
