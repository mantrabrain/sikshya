import {Center,Heading} from "@chakra-ui/react";

import * as React from 'react';
import {__} from '@wordpress/i18n';

const Header = () => {

	return (
		<Center p={50} w="full">
			<Heading>{__("Sikshya WordPress LMS Plugin", "sikshya")}</Heading>
		</Center>

	);
};
export default Header
