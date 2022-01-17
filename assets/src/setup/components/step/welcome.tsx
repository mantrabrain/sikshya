import * as React from 'react';
 import {Heading, Text} from "@chakra-ui/react";
import {__} from '@wordpress/i18n';

type WelcomeProps = {
	index: number
}

const Welcome = (props: WelcomeProps) => {

	return (
		<div>

			<Heading size='lg' fontSize='30px' marginBottom={10}>
				{__("Welcome to Sikshya LMS", "sikshya")}
			</Heading>
			<Text>{__("Thank you for choosing Yatra plugin for your travel & tour booking site. This setup wizard will help you configure the basic settings of the plugin. It’s completely optional and shouldn’t take longer than one minutes.")}</Text>


			<Text
				marginTop={10}>{__("No time right now? If you don’t want to go through the wizard, you can skip and return to the WordPress dashboard.")}</Text>

		</div>
	);
};
export default Welcome
