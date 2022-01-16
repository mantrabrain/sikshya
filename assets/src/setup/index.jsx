import React, {useEffect, useState, useRef} from "react";
import {Container, extendTheme, ChakraProvider} from "@chakra-ui/react";
import {StepsStyleConfig as Steps} from 'chakra-ui-steps';
import {render} from 'react-dom';
import './style.scss';
import Body from "./components/body";
import Header from "./components/header";
import Footer from "./components/footer";
import {Theme} from "./global/theme.js";

const SikshyaSetupWizard = () => {

	return (

		<ChakraProvider theme={Theme}>
			<Container>
				<Header/>
				<Body><p>Hello Guys</p></Body>
				<Footer/>
			</Container>
		</ChakraProvider>
	)
};
window.addEventListener("load", function () {
	render(
		<SikshyaSetupWizard/>,
		document.getElementById("sikshya-setup-element")
	);


});
