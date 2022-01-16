import * as React from 'react';
import {Box} from "@chakra-ui/react";

type StepContentProps = {
	index: number
}
const StepContent = (props: StepContentProps) => {

	return (
		<Box
			bg="white"
			w="100%"
			h="40vh"
			marginTop={20}
			marginBottom={20}
			borderRadius={5}
			p={4}
			color="gray.700"
			boxShadow="lg"
		>
			This is the Box {props.index}
		</Box>
	);
};
export default StepContent
