/** @var sikshyaSetup */
import * as React from 'react';
import {Flex, Button, IconButton, Link} from "@chakra-ui/react";

import {__} from '@wordpress/i18n';

type StepFooterProps = {
	activeStep: number,
	prevStep: any,
	nextStep: any,
	steps: Array<any>
};
const StepFooter = (props: StepFooterProps) => {
	let go_back_text = __('Go back to Dashboard', "sikshya");
	if (props.steps.length == (props.activeStep + 1)) {
		go_back_text = __('Create new course', 'sikshya');
	}
	return (
		<Flex width="100%" justify="space-between" align="center" gap={10} marginTop={10}>
			{props.activeStep > 0 ?
				<Button size="md" colorScheme="blue" onClick={props.prevStep}>
					{__('Back', 'sikshya')}
				</Button>
				: ""}

			<Link href={sikshyaSetup.course_page_url}>
				<Button
					size="md"
					colorScheme='blue'
					variant="outline"
				>
					{go_back_text}

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
