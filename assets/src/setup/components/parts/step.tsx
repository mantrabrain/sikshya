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
import {GeneralSettings} from "../../types/general-settings";
import SetupAPI from "../../api/setup-api";
import {PagesSettings} from "../../types/pages-settings";

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
	const [generalSettings, setGeneralSettings] = useState<GeneralSettings>();
	const [pagesSettings, setPagesSettings] = useState<PagesSettings>();

	const {updateGeneralSetting, updatePageSetting} = new SetupAPI;


	if (activeStep === 2 && generalSettings) {
		updateGeneralSetting(generalSettings)
		setGeneralSettings(null);

	} else if (activeStep === 3 && pagesSettings) {
		updatePageSetting(pagesSettings);
		setPagesSettings(null);
	}
	const renderStepView = (id: string) => {
		switch (id) {
			case 'welcome':
				return <Welcome index={1}/>;
			case 'general':
				return <General updateSettings={setGeneralSettings}/>;
			case 'pages':
				return <Pages updateSettings={setPagesSettings}/>;
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
