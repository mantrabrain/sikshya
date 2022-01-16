import * as React from 'react';
import {Box} from "@chakra-ui/react";
import SocialProfile from "./test";

type StepContentProps = {
	index: number
}

const StepContent = (props: StepContentProps) => {

	// @ts-ignore
	return (
		<Box
			bg="white"
			w="100%"
			h="auto"
			minHeight="200px"
			marginTop={20}
			marginBottom={20}
			borderRadius={5}
			p={4}
			color="gray.700"
			boxShadow="2xl"
		>
			<div>
				<h1>This is the Box {props.index}</h1>

			</div>
		</Box>
	);
};
export default StepContent
