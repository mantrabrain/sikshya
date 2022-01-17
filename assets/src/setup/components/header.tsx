import {Center} from "@chakra-ui/react";

import * as React from 'react';
import {__} from '@wordpress/i18n';

const Header = () => {

	return (
		<Center p={50} w="full">
			<h1>{__("Sikshya WordPress LMS Plugin", "sikshya")}</h1>
		</Center>

	);
};
export default Header
