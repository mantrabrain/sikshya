/** @var sikshyaSetup */
import * as React from 'react';
import {Flex, Button, IconButton, Link} from "@chakra-ui/react";

import {__} from '@wordpress/i18n';
import Global from './../../global/globals'

type StepFooterProps = {
	activeStep: number,
	prevStep: any,
	nextStep: any,
	steps: Array<any>
};

const StepFooter = (props: StepFooterProps) => {
	return (
		<Flex width="100%" justify="space-between" align="center" gap={10} marginTop={10}>
			{props.activeStep > 0 ?
				<Button size="md" colorScheme="blue" onClick={props.prevStep} >
					{__('Back', 'sikshya')}
				</Button>
				: ""}

			<Link href={Global.course_page_url}>
				<Button
					size="md"
					colorScheme='blue'
					variant="outline"
				>
					{__('Go back to Dashboard', "sikshya")}

				</Button>
			</Link>

			{props.steps.length > (props.activeStep + 1) ?
				<Button size="md" colorScheme="blue" onClick={props.nextStep}>
					{__('Next', 'sikshya')}
				</Button>
				: ""}

		</Flex>
	);
};
export default StepFooter
