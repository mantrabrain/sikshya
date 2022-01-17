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
			minHeight={300}
			marginTop={10}
			marginBottom={10}
			borderRadius={5}
			p={25}
			color="gray.700"
			boxShadow="xl"
		>
			{children}

		</Box>
	);
};
export default StepBox
