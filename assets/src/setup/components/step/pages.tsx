import * as React from 'react';
import {Flex, FormControl, FormLabel, Select} from "@chakra-ui/react";

type PagesProps = {
	index: number
}

const Pages = (props: PagesProps) => {

	return (
		<Flex flexDir="column" width="100%" gap={5}>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='account-page'>Account Page</FormLabel>
					<Select id='account-page' placeholder='Select Account Page' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='registration-page'>Registration Page</FormLabel>
					<Select id='registration-page' placeholder='Select Registration Page' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='login-page'>Login Page</FormLabel>
					<Select id='login-page' placeholder='Select Login Page' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='cart-page'>Cart Page</FormLabel>
					<Select id='cart-page' placeholder='Select Cart Page' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>
			<FormControl>
				<Flex justify="space-between" width="full" align="center">
					<FormLabel htmlFor='checkout-page'>Checkout Page</FormLabel>
					<Select id='checkout-page' placeholder='Select Checkout Page' w="md">
						<option>United Arab Emirates</option>
						<option>Nigeria</option>
					</Select>
				</Flex>
			</FormControl>


		</Flex>
	);
};
export default Pages
