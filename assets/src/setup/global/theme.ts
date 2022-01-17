import * as React from 'react';
import {Container, extendTheme, ChakraProvider} from "@chakra-ui/react";
import {StepsStyleConfig as Steps} from 'chakra-ui-steps';
import Styles from "./theme/styles";
import Button from "./theme/components/Button";

const Theme = extendTheme({
	components: {
		Steps,
		Button
	},
	initialColorMode: 'light',
	size: {
		container: "900px"
	},
	styles: Styles


});
export default Theme
