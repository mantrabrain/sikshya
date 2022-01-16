import * as React from 'react';
import {Box} from "@chakra-ui/react";

type SetupBoxProps = {
	children: React.ReactNode;
};
const StepBox = ({children}: SetupBoxProps) => {
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
			{children}

		</Box>
	);
};
export default StepBox
