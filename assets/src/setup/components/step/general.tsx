import * as React from 'react';
import {useState, useEffect} from 'react';
import {
	Flex, FormControl, FormLabel, Select, Input, NumberInput,
	NumberInputField,
	NumberInputStepper,
	NumberIncrementStepper,
	NumberDecrementStepper
} from "@chakra-ui/react";
import SikshyaAPIFetch from "../../global/api";
import Paragraph_Skeleton from "../../skeleton/paragraph";
import StepBox from "../parts/stepbox";
import StepFooter from "./step-footer";

type GeneralProps = {
	index: number
}
type APIResponseType = {
	currency: string,
	currency_symbol_type: string,
	currency_position: string,
	thousand_separator: string,
	price_number_decimals: number,
	decimal_separator: string,

}
const General = (props: GeneralProps) => {

	const [apiResponse, setApiResponse] = useState<APIResponseType>();
	const apiCall = () => {
		new Promise<void>((resolve, reject) => {
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings',
				method: 'GET',
			}).then((response) => {
				setApiResponse(response);
			});
		});
	}
	useEffect(() => {

		let size = !apiResponse ? 0 : Object.keys(apiResponse).length;
		if (size == 0) {
			apiCall();
		}
	}, [apiResponse]);


	let size = !apiResponse ? 0 : Object.keys(apiResponse).length;
	if (size < 1) {

		return (<Paragraph_Skeleton/>);

	}

	const renderOptions = (key: string, value: string) => {
		return (
			<option value={key}>
				{value}
			</option>
		)
	}
	// @ts-ignore
	return (
		<Flex flexDir="column" width="100%" gap={5}>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency'>Currency</FormLabel>
					<Select id='currency' placeholder='Select currency' w="md">
						{Object.keys(sikshyaSetup.currencies).map((currency_key: string, index: number) => (
							<option selected={currency_key === apiResponse.currency}
									value={currency_key}>{sikshyaSetup.currencies[currency_key]}</option>
						))}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency-symbol-type'>Currency Symbol Type</FormLabel>
					<Select id='currency-symbol-type' placeholder='Currency Symbol Type' w="md">
						{Object.keys(sikshyaSetup.currency_symbol_type).map((symbol_type_key: string, index: number) => (
							<option selected={symbol_type_key === apiResponse.currency_symbol_type}
									value={symbol_type_key}>{sikshyaSetup.currency_symbol_type[symbol_type_key]}</option>
						))}

					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency-position'>Currency Position</FormLabel>
					<Select id='currency-position' placeholder='Currency Position' w="md">
						{Object.keys(sikshyaSetup.currency_positions).map((position_key: string, index: number) => (
							<option selected={position_key === apiResponse.currency_position}
									value={position_key}>{sikshyaSetup.currency_positions[position_key]}</option>
						))}

					</Select>
				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='thousand-separator'>Thousand Separator</FormLabel>
					<Input id='thousand-separator' placeholder='Thousand Separator' w="md"
						   value={apiResponse.thousand_separator}/>

				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='number-of-decimals'>Number Of Decimals</FormLabel>

					<NumberInput id='number-of-decimals' defaultValue={apiResponse.price_number_decimals} max={10}
								 clampValueOnBlur={false} w="md">
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
					<FormLabel htmlFor='decimal-separator'>Decimal Separator</FormLabel>

					<Input id='decimal-separator' placeholder='Decimal Separator' w="md"
						   value={apiResponse.decimal_separator}/>
				</Flex>
			</FormControl>
		</Flex>
	);
};
export default General
