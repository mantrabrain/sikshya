import {Heading, Box, Flex, Button, Center} from "@chakra-ui/react";
import {Step, Steps, useSteps} from "chakra-ui-steps";
import {ArrowBackIcon, ArrowForwardIcon} from "@chakra-ui/icons";
import * as React from 'react';
import StepContent from "./step-content";

const steps = [{label: "Welcome"}, {label: "Course"}, {label: "Pages"}, {label: "Finish"}]

export const ClickableSteps = () => {
	const {nextStep, prevStep, reset, activeStep, setStep} = useSteps({
		initialStep: 0,
	})

	return (
		<Flex flexDir="column" width="100%">
			<Steps onClickStep={(step) => setStep(step)} activeStep={activeStep}>
				{steps.map(({label}, index) => (
					<Step label={label} key={label}>
						<StepContent index={index}/>
					</Step>
				))}
			</Steps>
			{activeStep === 3 ? (
				<Center p={4} flexDir="column">
					<Heading fontSize="xl">Woohoo! All steps completed!</Heading>
					<Button mt={6} size="sm" onClick={reset}>
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
