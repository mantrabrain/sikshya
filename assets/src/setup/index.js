import React, {useEffect, useState, useRef} from "react";
import {render} from 'react-dom';
import './style.scss';


const SikshyaSetupElement = () => {


	return (
		<h1>Hello World Guys</h1>
	)
};
window.addEventListener("load", function () {
	render(
		<SikshyaSetupElement/>,
		document.getElementById("sikshya-setup-element")
	);


});
