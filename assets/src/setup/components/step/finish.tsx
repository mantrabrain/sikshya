import * as React from 'react';
import {Heading, Center, Icon, Flex} from "@chakra-ui/react";
import {CheckCircleIcon} from '@chakra-ui/icons'

const Finish = () => {

	return (
		<div>

			<Heading m="0 auto" align="center" fontSize="xl">Woohoo!! Congratulations, Sikshya Setup
				completed!</Heading>
			<Flex justify="space-between" align="center" width="100%" marginTop={10}>

				<CheckCircleIcon w={250} h={250} color="green.500" m="0 auto"/>


			</Flex>

		</div>
	);
};
export default Finish
