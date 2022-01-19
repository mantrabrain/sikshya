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
import {StepItem} from "../../types/step-item";


export const ClickableSteps = () => {
	const {nextStep, prevStep, reset, activeStep, setStep} = useSteps({
		initialStep: 0,
	})
	const [generalSettings, setGeneralSettings] = useState<GeneralSettings>();
	const [pagesSettings, setPagesSettings] = useState<PagesSettings>();

	const {updateGeneralSetting, updatePageSetting} = new SetupAPI;
	const getActiveStepID = (step_id: number) => {
		let sikshyaSteps = sikshyaSetup.steps;

		if (typeof sikshyaSteps[step_id] !== undefined) {

			return sikshyaSteps[step_id].id;
		}
		return undefined;

	}
	if (getActiveStepID(activeStep) === "general" && generalSettings) {
		updateGeneralSetting(generalSettings)
		setGeneralSettings(null);

	} else if (getActiveStepID(activeStep) === "pages" && pagesSettings) {
		updatePageSetting(pagesSettings);
		setPagesSettings(null);
	}
	const renderStepView = (id: string) => {
		switch (id) {
			case 'welcome':
				return <Welcome/>;
			case 'general':
				return <General updateSettings={setGeneralSettings}/>;
			case 'pages':
				return <Pages updateSettings={setPagesSettings}/>;
			case 'finish':
				return <Finish/>;
			case 'themes':
				return <Themes/>;
			default:
				return <Heading type="h2">Sorry! Component not found.</Heading>;
		}
	}

	// @ts-ignore
	return (
		<Flex flexDir="column" width="100%">
			<Steps onClickStep={(step) => setStep(step)} activeStep={activeStep}>

				{sikshyaSetup.steps.map((step_item: StepItem, index: any) => (
					<Step label={step_item.label} key={step_item.label}>
						<StepBox>
							{renderStepView(step_item.id)}
							<StepFooter activeStep={activeStep} prevStep={prevStep} nextStep={nextStep}
										steps={sikshyaSetup.steps}/>
						</StepBox>

					</Step>
				))}

			</Steps>
		</Flex>
	)
}
export default ClickableSteps
