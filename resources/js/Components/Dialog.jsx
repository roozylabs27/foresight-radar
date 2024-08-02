import { Button, Form, Modal } from "antd";

export default function Dialog({
    title,
    open,
    isEditMode = false,
    loading,
    onCancel,
    onOk,
    children,
}) {
    const [form] = Form.useForm();

    const handleFormSubmit = () => {
        form.submit();
    }

    return (
        <>
            <Modal
                title={title}
                open={open}
                onCancel={onCancel}
                footer={[
                    <Button key="back" onClick={onCancel}>
                        Cancel
                    </Button>,
                    <Button
                        key="submit"
                        type="primary"
                        loading={loading}
                        onClick={handleFormSubmit}
                    >
                        {isEditMode ? "Update" : "Create"}
                    </Button>,
                ]}
            >
                {children}
            </Modal>
        </>
    );
}
