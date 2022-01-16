import React, {useEffect, useState, useRef} from "react";
import {render} from 'react-dom';
import './style.scss';
import Body from "./components/body";
import Header from "./components/header";
import Footer from "./components/footer";


const SikshyaSetupWizard = () => {

	return (
		<>
			<Header/>
			<Body/>
			<Footer/>
		</>
	)
};
window.addEventListener("load", function () {
	render(
		<SikshyaSetupWizard/>,
		document.getElementById("sikshya-setup-element")
	);


});
