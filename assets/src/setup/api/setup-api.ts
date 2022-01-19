import * as React from 'react';
import SikshyaAPIFetch from "../global/api";
import {GeneralSettings} from "../types/general-settings";

class SetupAPI {

	initGeneralSetting(callback: any): void {
		new Promise<void>((resolve, reject) => {
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings/general/',
				method: 'GET',
			}).then((response: GeneralSettings) => {
				if (typeof callback === "function") {
					callback(response);
				}
			});
		});
	}

	initPagesSetting(callback: any): void {
		new Promise<void>((resolve, reject) => {
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings/pages/',
				method: 'GET',
			}).then((response: GeneralSettings) => {
				if (typeof callback === "function") {
					callback(response);
				}
			});
		});
	}

	updateGeneralSetting(postData: any, responseCallback?: any): void {
		new Promise<void>((resolve, reject) => {
			// @ts-ignore
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings/general/update',
				method: 'POST',
				data: postData
			}).then((response: any) => {
				if (typeof responseCallback === "function") {
					responseCallback(response);
				}
			});
		});
	}

	updatePageSetting(postData: any, responseCallback?: any): void {
		new Promise<void>((resolve, reject) => {
			// @ts-ignore
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings/pages/update',
				method: 'POST',
				data: postData
			}).then((response: any) => {
				if (typeof responseCallback === "function") {
					responseCallback(response);
				}
			});
		});
	}

	initThemeStatus(callback: any, getData: any): void {
		new Promise<void>((resolve, reject) => {
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings/themes/',
				method: 'POST',
				data: getData
			}).then((response: GeneralSettings) => {
				if (typeof callback === "function") {
					callback(response);
				}
			});
		});
	}

	initThemeAction(callback: any, getData: any): void {

		new Promise<void>((resolve, reject) => {
			SikshyaAPIFetch({
				path: '/sikshya/v1/settings/themes/action',
				method: 'POST',
				data: getData
			}).then((response: GeneralSettings) => {
				if (typeof callback === "function") {
					callback(response);
				}
			}).catch((response: any) => {
				if (typeof callback === "function") {
					callback(response);
				}
			});
		});
	}


}

export default SetupAPI;
