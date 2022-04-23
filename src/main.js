/* eslint-disable */
/**
 * @copyright Copyright (c) 2021 Patrick Herzberg <patrick@westberliner.net>
 *
 * @author Patrick Herzberg <patrick@westberliner.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import {translate as t, translatePlural as n} from '@nextcloud/l10n'

import AgeTab from './App'

Vue.prototype.t = t
Vue.prototype.n = n
const AgeTabView = Vue.extend(AgeTab)
let AgeTabInstance = null

const ageTab = new OCA.Files.Sidebar.Tab({
    id: 'age',
    name: t('age', 'age'),
    icon: 'icon-delete',

    async mount(el, fileInfo, context) {
        if (AgeTabInstance) {
            AgeTabInstance.$destroy()
        }
        AgeTabInstance = new AgeTabView({
            // Better integration with vue parent component
            parent: context,
        })
        // Only mount after we have all the info we need
        await AgeTabInstance.update(fileInfo)
        AgeTabInstance.$mount(el)
    },
    update(fileInfo) {
        AgeTabInstance.update(fileInfo)
    },
    destroy() {
        AgeTabInstance.$destroy()
        AgeTabInstance = null
    },
})


window.addEventListener('DOMContentLoaded', function () {
    if (OCA.Files && OCA.Files.Sidebar) {
        OCA.Files.Sidebar.registerTab(ageTab)
    }
})
