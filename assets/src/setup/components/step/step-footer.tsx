import * as React from 'react';

import {Flex, Button} from "@chakra-ui/react";
import {__} from '@wordpress/i18n';

type StepFooterProps = {
	activeStep: number,
	prevStep: any,
	nextStep: any,
};

const StepFooter = (props: StepFooterProps) => {

	return (
		<Flex width="100%" justify="center" align="center" gap={10}>
			<Button
				size="md"
				colorScheme='blue'
				variant="link"

			>
				Not right now {props.activeStep}

			</Button>
			<Button size="md" colorScheme="blue" onClick={props.nextStep}>
				Next
			</Button>

		</Flex>
	);
};
export default StepFooter
