Centered modal with navy scrim; closes on Escape / scrim click.

```jsx
<Dialog open={open} onClose={close} title="Confirm booking"
  footer={<><Button variant="ghost" onClick={close}>Cancel</Button><Button variant="accent">Confirm</Button></>}>
  Court 4 · Saturday 09:00–10:00
</Dialog>
```
