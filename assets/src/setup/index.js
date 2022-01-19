import React from "react";
import {Container, VStack, HStack, ChakraProvider} from "@chakra-ui/react";
import {render} from 'react-dom';
import Body from "./components/body";
import Header from "./components/header";
import Footer from "./components/footer";
import Theme from "./global/theme";

const SikshyaSetupWizard = () => {

	return (

		<ChakraProvider theme={Theme}>
			<Container maxW="container.md">
				<VStack  h="100vh" alignItems="center" justifyContent="space-between">
					<Header/>
					<Body/>
					<Footer/>
				</VStack>
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
