import {Heading, Box, Flex, Button, Center} from "@chakra-ui/react";
import {Step, Steps, useSteps} from "chakra-ui-steps";
import {ArrowBackIcon, ArrowForwardIcon} from "@chakra-ui/icons";
import * as React from 'react';
import Welcome from "./../step/welcome";
import General from "./../step/general";
import Pages from "./../step/pages";
import Finish from "./../step/finish";
import StepFooter from "../step/step-footer";
import StepBox from "./stepbox";
import Themes from "../step/themes";

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
	const renderStepView = (id: string) => {

		switch (id) {
			case 'welcome':
				return <Welcome index={1}/>;
			case 'general':
				return <General index={1}/>;
			case 'pages':
				return <Pages index={1}/>;
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
