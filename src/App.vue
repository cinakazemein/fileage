<template>
  <Content :class="{'icon-loading': loading, 'd-block': true }" app-name="vueexample">
    <input type="text"
		   v-model="age"
		   placeholder="number of days to delete(content)"
		   class="w-100" />
	  <br>

    <button @click="sendFileAge"
			class="button bg-warning w-100">submit</button>
  </Content>
</template>

<script>
/* eslint-disable */
import Content from '@nextcloud/vue/dist/Components/Content'
import axios from '@nextcloud/axios'
import {generateUrl} from '@nextcloud/router'

export default {
  name: 'App',
  components: {
    Content,
  },
  data() {
    return {
      loading: false,
      show: true,
      starred: false,
      age: '',
      fileInfo: '',
    }
  },
  methods: {
    update(fileInfo) {
      this.fileInfo = fileInfo
      console.log(fileInfo);
    },
    sendFileAge() {
      const url = generateUrl('/apps/fileage')
      console.log(this.fileInfo)
      const body = {fileInfo: this.fileInfo, age: this.age}
      axios.post(url, body)
          .then(function (response) {
            console.log(response);
          })
          .catch(function (error) {
            console.log(error);
          });
    },
  },
  close() {
    this.show = false
    console.debug(arguments)
  },
  newButtonAction() {
    console.debug(arguments)
  },
  log() {
    console.debug(arguments)
  },
}
</script>
<style lang="scss" scoped>
	.w-100{
		width: 100%;
	}

	.d-block{
		display: block;
	}
</style>
