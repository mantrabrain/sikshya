import {Heading, Box, Flex, Button, Center} from "@chakra-ui/react";
import {Step, Steps, useSteps} from "chakra-ui-steps";
import {ArrowBackIcon, ArrowForwardIcon} from "@chakra-ui/icons";
import * as React from 'react';
import Welcome from "./../step/welcome";
import Course from "./../step/course";
import Pages from "./../step/pages";
import Finish from "./../step/finish";

const steps = [
	{label: "Welcome", "id": "welcome"},
	{label: "Course", id: "course"},
	{label: "Pages", id: "pages"},
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
			case 'course':
				return <Course index={1}/>;
			case 'pages':
				return <Pages index={1}/>;
			case 'finish':
				return <Finish index={1}/>;
			default:
				return "<h2>Component {id} not found</h2>";
		}
	}

	return (
		<Flex flexDir="column" width="100%">
			<Steps onClickStep={(step) => setStep(step)} activeStep={activeStep}>
				{steps.map(({label, id}, index) => (
					<Step label={label} key={label}>
						{renderStepView(id)}
					</Step>
				))}
			</Steps>
			{activeStep === 3 ? (
				<Center p={4} flexDir="column">
					<Heading fontSize="xl">Woohoo! All steps completed!</Heading>
					<Button mt={6} size="sm" onClick={reset} varient="with-shadow">
						Reset
					</Button>
				</Center>
			) : (
				<Flex width="100%" justify="flex-end">
					<Button
						mr={4}
						size="sm"
						variant="ghost"
						onClick={prevStep}
						isDisabled={activeStep === 0}
					>
						Prev
					</Button>
					<Button size="sm" onClick={nextStep}>
						{activeStep === steps.length - 1 ? "Finish" : "Next"}
					</Button>
				</Flex>
			)}
		</Flex>
	)
}
export default ClickableSteps
