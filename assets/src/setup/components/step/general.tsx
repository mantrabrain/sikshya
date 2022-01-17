import * as React from 'react';
import {
	Flex, FormControl, FormLabel, Select, Input, NumberInput,
	NumberInputField,
	NumberInputStepper,
	NumberIncrementStepper,
	NumberDecrementStepper
} from "@chakra-ui/react";

type GeneralProps = {
	index: number
}

const General = (props: GeneralProps) => {

	return (
		<Flex flexDir="column" width="100%" gap={5}>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency'>Currency</FormLabel>
					<Select id='currency' placeholder='Select currency' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency-symbol-type'>Currency Symbol Type</FormLabel>
					<Select id='currency-symbol-type' placeholder='Currency Symbol Type' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency-position'>Currency Position</FormLabel>
					<Select id='currency-position' placeholder='Currency Position' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='thousand-separator'>Thousand Separator</FormLabel>
					<Input id='thousand-separator' placeholder='Thousand Separator' w="md"/>

				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='number-of-decimals'>Number Of Decimals</FormLabel>

					<NumberInput id='number-of-decimals' defaultValue={15} max={30} clampValueOnBlur={false} w="md">
						<NumberInputField/>
						<NumberInputStepper>
							<NumberIncrementStepper/>
							<NumberDecrementStepper/>
						</NumberInputStepper>
					</NumberInput>
				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='decimal-separator'>Currency Position</FormLabel>

					<Input id='decimal-separator' placeholder='Decimal Separator' w="md"/>
				</Flex>
			</FormControl>
		</Flex>
	);
};
export default General
