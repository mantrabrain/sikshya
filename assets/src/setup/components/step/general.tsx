import * as React from 'react';
import {useState, useEffect} from 'react';
import {
	Flex, FormControl, FormLabel, Select, Input, NumberInput,
	NumberInputField,
	NumberInputStepper,
	NumberIncrementStepper,
	NumberDecrementStepper,
	Slider,
	SliderTrack,
	SliderFilledTrack,
	SliderThumb
} from "@chakra-ui/react";
import Paragraph_Skeleton from "../../skeleton/paragraph";
import {GeneralSettings} from "../../types/general-settings";
import SetupAPI from "../../api/setup-api";


const General = (props: any) => {
	const [generalAPIResponse, setGeneralAPIResponse] = useState<GeneralSettings>();
	const {initGeneralSetting} = new SetupAPI;

	const callbackCall = (response: any) => {
		setGeneralAPIResponse(response);
		props.updateSettings(response);
	}
	useEffect(() => {

		let size = !generalAPIResponse ? 0 : Object.keys(generalAPIResponse).length;
		if (size == 0) {
			initGeneralSetting(callbackCall);

		}
	}, [generalAPIResponse]);

	const handleNumberOfDecimalChange = (value: any) => {
		generalAPIResponse.number_of_decimals = value;
		setGeneralAPIResponse(generalAPIResponse)
		props.updateSettings(generalAPIResponse)

	}

	const update = (event: any) => {

		const value = event.target.value;
		const id = event.target.id;
		if (generalAPIResponse.hasOwnProperty(id)) {
			// @ts-ignore
			generalAPIResponse[id] = value;
		}
		props.updateSettings(generalAPIResponse);
	}
	let size = !generalAPIResponse ? 0 : Object.keys(generalAPIResponse).length;
	if (size < 1) {

		return (<Paragraph_Skeleton/>);

	}

	// @ts-ignore
	return (
		<Flex flexDir="column" width="100%" gap={5}>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency'>Currency</FormLabel>
					<Select id='currency' placeholder='Select currency' w="md" onChange={update}>
						{Object.keys(sikshyaSetup.currencies).map((currency_key: string, index: number) => (
							<option selected={currency_key === generalAPIResponse.currency}
									value={currency_key}>{sikshyaSetup.currencies[currency_key]}</option>
						))}
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency_symbol_type'>Currency Symbol Type</FormLabel>
					<Select id='currency_symbol_type' placeholder='Currency Symbol Type' w="md" onChange={update}>
						{Object.keys(sikshyaSetup.currency_symbol_type).map((symbol_type_key: string, index: number) => (
							<option selected={symbol_type_key === generalAPIResponse.currency_symbol_type}
									value={symbol_type_key}>{sikshyaSetup.currency_symbol_type[symbol_type_key]}</option>
						))}

					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='currency_position'>Currency Position</FormLabel>
					<Select id='currency_position' placeholder='Currency Position' w="md" onChange={update}>
						{Object.keys(sikshyaSetup.currency_positions).map((position_key: string, index: number) => (
							<option selected={position_key === generalAPIResponse.currency_position}
									value={position_key}>{sikshyaSetup.currency_positions[position_key]}</option>
						))}

					</Select>
				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='thousand_separator'>Thousand Separator</FormLabel>
					<Input id='thousand_separator' placeholder='Thousand Separator' w="md"
						   defaultValue={generalAPIResponse.thousand_separator} onChange={update}/>

				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='number_of_decimals_input'>Number Of Decimals</FormLabel>
					<Flex justify="space-between" w="md">
						<NumberInput id='number_of_decimals_input' defaultValue={generalAPIResponse.number_of_decimals}
									 max={5}
									 min={0}
									 clampValueOnBlur={false} w="md"
									 onChange={handleNumberOfDecimalChange}>
							<NumberInputField defaultValue={generalAPIResponse.number_of_decimals} onChange={update}/>
							<NumberInputStepper>
								<NumberIncrementStepper/>
								<NumberDecrementStepper/>
							</NumberInputStepper>
						</NumberInput>

					</Flex>
				</Flex>
			</FormControl>

			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='decimal_separator'>Decimal Separator</FormLabel>

					<Input id='decimal_separator' placeholder='Decimal Separator' w="md"
						   defaultValue={generalAPIResponse.decimal_separator} onChange={update}/>
				</Flex>
			</FormControl>
		</Flex>
	);
};
export default General
