import {Container, extendTheme, Center} from "@chakra-ui/react";
import * as React from 'react';
import {StepsStyleConfig as Steps} from 'chakra-ui-steps';
import {__} from '@wordpress/i18n';

const Footer = () => {

	return (

		<Center p={50} w="full">
			<h1>{__("Sikshya WordPress LMS Plugin", "sikshya")}</h1>
		</Center>
	);
};
export default Footer
