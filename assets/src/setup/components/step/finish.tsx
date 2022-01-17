import * as React from 'react';
import {Heading, Center} from "@chakra-ui/react";

type FinishProps = {
	index: number
}

const Finish = (props: FinishProps) => {

	return (
		<Center>

			<Heading fontSize="xl">Woohoo! All steps completed!</Heading>

		</Center>
	);
};
export default Finish
