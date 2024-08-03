import { Button, Form, Modal } from "antd";

export default function Dialog({
    title,
    open,
    onCancel,
    isEditMode,
    onOk,
    loading,
    children,
}) {
    return (
        <>
            <Modal
                title={title}
                open={open}
                loading={loading}
                onCancel={onCancel}
                footer={[
                    <Button key="back" onClick={onCancel}>
                        Cancel
                    </Button>,
                    <Button
                        key="submit"
                        type="primary"
                        loading={loading}
                        onClick={onOk}
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
