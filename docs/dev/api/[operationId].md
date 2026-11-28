---
aside: false
outline: false
---

<script setup>
import { useRoute } from 'vitepress'

const route = useRoute()
</script>

<OAOperation :operationId="route.data.params.operationId" />
