import * as React from 'react';
import StepBox from "./../parts/stepbox";

type WelcomeProps = {
	index: number
}

const Welcome = (props: WelcomeProps) => {

	return (
		<StepBox>
			<h1>Welcome to Sikshya LMS!</h1>
			<p>Thank you for choosing Yatra plugin for your travel & tour booking site. This setup wizard will help you
				configure the basic settings of the plugin. It’s completely optional and shouldn’t take longer than one
				minutes.
			</p>
			<p>No time right now? If you don’t want to go through the wizard, you can skip and return to the WordPress
				dashboard.
			</p>
		</StepBox>
	);
};
export default Welcome
