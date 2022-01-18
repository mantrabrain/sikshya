import {Heading, Box, Flex, Button, Center} from "@chakra-ui/react";
import {Step, Steps, useSteps} from "chakra-ui-steps";
import * as React from 'react';
import Welcome from "./../step/welcome";
import General from "./../step/general";
import Pages from "./../step/pages";
import Finish from "./../step/finish";
import StepFooter from "../parts/step-footer";
import StepBox from "./stepbox";
import Themes from "../step/themes";
import {useState, useEffect} from 'react';
import {Settings} from "../../types/settings";
import SikshyaAPIFetch from "../../global/api";

const steps = [
	{label: "Welcome", "id": "welcome"},
	{label: "General", id: "general"},
	{label: "Pages", id: "pages"},
	{label: "Themes", id: "themes"},
	{label: "Finish", id: "finish"}
]

export const ClickableSteps = () => {
	const {nextStep, prevStep, reset, activeStep, setStep} = useSteps({
		initialStep: 0,
	})
	const [settings, setSettings] = useState<Settings>();

	const updateSettingItem = (id: string, value: any) => {
		let all_settings = settings;
		// @ts-ignore
		if (all_settings.hasOwnProperty(id)) {
			// @ts-ignore
			all_settings[id] = value;//Settings[id] === "number" ? value.parseInt() : value;
		}

		setSettings(all_settings);

		console.log(settings);


	}
	if (activeStep === 2 || activeStep === 3) {
		new Promise<void>((resolve, reject) => {
			// @ts-ignore
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings/update',
				method: 'POST',
				data: settings
			}).then((response: any) => {
				console.log(response);
			});
		});
	}
	const renderStepView = (id: string) => {

		switch (id) {
			case 'welcome':
				return <Welcome index={1}/>;
			case 'general':
				return <General updateSettings={setSettings}
								updateSettingItem={updateSettingItem}/>;
			case 'pages':
				return <Pages updateSettings={setSettings}
							  updateSettingItem={updateSettingItem}/>;
			case 'finish':
				return <Finish index={activeStep}/>;
			case 'themes':
				return <Themes index={1}/>;
			default:
				return "<h2>Component {id} not found</h2>";
		}
	}

	return (
		<Flex flexDir="column" width="100%">
			<Steps onClickStep={(step) => setStep(step)} activeStep={activeStep}>
				{steps.map(({label, id}, index) => (
					<Step label={label} key={label}>
						<StepBox>
							{renderStepView(id)}
							<StepFooter activeStep={activeStep} prevStep={prevStep} nextStep={nextStep}
										steps={steps}/>
						</StepBox>

					</Step>
				))}

			</Steps>
		</Flex>
	)
}
export default ClickableSteps
